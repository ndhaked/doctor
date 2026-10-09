<?php

namespace App\Support;

use App\Models\Appointment;
use App\Models\Consultation;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\PrescriptionItem;
use App\Models\Staff;
use App\Models\StaffAttendance;
use App\Models\StaffPayment;
use App\Models\StaffTask;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Builds the clinic reports. Every report returns the same shape so one screen (and one CSV export) can show any of them:
 *   ['title' => string, 'summary' => [label => value], 'headings' => [..], 'rows' => [[..], ..]]
 */
class ClinicReports
{
    /** Report type => [title, permission needed]. */
    public const TYPES = [
        'patients' => ['Patients', 'manage patients'],
        'appointments' => ['Appointments', 'manage appointments'],
        'consultations' => ['Consultations', 'view medical records'],
        'treatments' => ['Treatments & medicines', 'view medical records'],
        'revenue' => ['Revenue', 'manage billing'],
        'pending' => ['Pending payments', 'manage billing'],
        'staff' => ['Staff', 'manage staff'],
    ];

    public static function build(string $type, Carbon $from, Carbon $to, int $doctorId): array
    {
        $from = $from->copy()->startOfDay();
        $to = $to->copy()->endOfDay();

        return match ($type) {
            'patients' => self::patients($from, $to, $doctorId),
            'appointments' => self::appointments($from, $to, $doctorId),
            'consultations' => self::consultations($from, $to, $doctorId),
            'treatments' => self::treatments($from, $to, $doctorId),
            'revenue' => self::revenue($from, $to, $doctorId),
            'pending' => self::pending($doctorId),
            'staff' => self::staff($from, $to, $doctorId),
            default => abort(404),
        };
    }

    protected static function money($value): string
    {
        return ClinicSettings::money($value);
    }

    protected static function patients(Carbon $from, Carbon $to, int $doctorId): array
    {
        $patients = Patient::forDoctor($doctorId)->whereBetween('created_at', [$from, $to])->orderByDesc('created_at')->get();

        return [
            'title' => 'Patient report',
            'summary' => [
                'New patients' => $patients->count(),
                'Male' => $patients->where('gender', 'male')->count(),
                'Female' => $patients->where('gender', 'female')->count(),
                'Other / not given' => $patients->filter(fn ($p) => ! in_array($p->gender, ['male', 'female'], true))->count(),
                'All patients (total)' => Patient::forDoctor($doctorId)->count(),
            ],
            'headings' => ['Number', 'Name', 'Gender', 'Age', 'Phone', 'Registered'],
            'rows' => $patients->map(fn ($p) => [$p->uhid, $p->name, $p->gender ? Patient::GENDERS[$p->gender] : '', $p->age() ?? '', $p->phone, $p->created_at->format('d M Y')])->all(),
        ];
    }

    protected static function appointments(Carbon $from, Carbon $to, int $doctorId): array
    {
        $all = Appointment::forDoctor($doctorId)->whereBetween('scheduled_at', [$from, $to])->get();

        $summary = ['Total appointments' => $all->count()];

        foreach (Appointment::STATUSES as $key => $label) {
            $summary[$label] = $all->where('status', $key)->count();
        }

        $summary['New visits'] = $all->where('type', 'new')->count();
        $summary['Follow-ups'] = $all->where('type', 'follow_up')->count();

        $rows = $all->groupBy(fn ($a) => $a->scheduled_at->format('Y-m-d'))->sortKeys()->map(fn (Collection $day, $date) => [
            Carbon::parse($date)->format('d M Y, D'),
            $day->count(),
            $day->where('status', 'completed')->count(),
            $day->where('status', 'cancelled')->count(),
            $day->where('status', 'no_show')->count(),
        ])->values()->all();

        return ['title' => 'Appointment report', 'summary' => $summary, 'headings' => ['Date', 'Appointments', 'Completed', 'Cancelled', 'No show'], 'rows' => $rows];
    }

    protected static function consultations(Carbon $from, Carbon $to, int $doctorId): array
    {
        $all = Consultation::forDoctor($doctorId)->with('patient')->whereBetween('consulted_at', [$from, $to])->orderByDesc('consulted_at')->get();

        $top = $all->filter(fn ($c) => filled($c->diagnosis))->groupBy(fn ($c) => mb_strtolower(trim($c->diagnosis)))->map->count()->sortDesc()->keys()->first();

        return [
            'title' => 'Consultation report',
            'summary' => ['Consultations' => $all->count(), 'Completed' => $all->where('status', 'final')->count(), 'Drafts' => $all->where('status', 'draft')->count(), 'Most common diagnosis' => $top ? ucfirst($top) : '—'],
            'headings' => ['Date', 'Patient', 'Complaint', 'Diagnosis', 'Status'],
            'rows' => $all->map(fn ($c) => [$c->consulted_at->format('d M Y'), $c->patient->name, (string) $c->chief_complaint, (string) $c->diagnosis, $c->isFinal() ? 'Completed' : 'Draft'])->all(),
        ];
    }

    protected static function treatments(Carbon $from, Carbon $to, int $doctorId): array
    {
        $consultations = Consultation::forDoctor($doctorId)->whereBetween('consulted_at', [$from, $to])->get(['id', 'patient_id', 'treatment']);
        $items = PrescriptionItem::whereIn('consultation_id', $consultations->pluck('id'))->get();
        $patientByConsultation = $consultations->pluck('patient_id', 'id');

        $rows = $items->groupBy(fn ($i) => mb_strtolower(trim($i->medicine)))->map(fn (Collection $group) => [
            $group->first()->medicine,
            $group->count(),
            $group->map(fn ($i) => $patientByConsultation[$i->consultation_id] ?? null)->unique()->count(),
        ])->sortByDesc(fn ($r) => $r[1])->values()->all();

        return [
            'title' => 'Treatment & medicine report',
            'summary' => [
                'Consultations with a treatment recorded' => $consultations->filter(fn ($c) => filled($c->treatment))->count(),
                'Prescriptions written' => $items->pluck('consultation_id')->unique()->count(),
                'Different medicines used' => count($rows),
            ],
            'headings' => ['Medicine', 'Times prescribed', 'Patients'],
            'rows' => $rows,
        ];
    }

    protected static function revenue(Carbon $from, Carbon $to, int $doctorId): array
    {
        $invoices = Invoice::forDoctor($doctorId)->where('status', '!=', 'cancelled')->whereDate('invoice_date', '>=', $from->toDateString())->whereDate('invoice_date', '<=', $to->toDateString())->get();
        $payments = Payment::whereHas('invoice', fn ($q) => $q->forDoctor($doctorId))->whereBetween('paid_at', [$from, $to])->get();
        $items = InvoiceItem::whereIn('invoice_id', $invoices->pluck('id'))->get();

        $summary = [
            'Invoiced' => self::money($invoices->sum('total')),
            'Collected' => self::money($payments->sum('amount')),
        ];

        foreach (\App\Models\Payment::METHODS as $key => $label) {
            $summary["Collected by $label"] = self::money($payments->where('method', $key)->sum('amount'));
        }

        foreach (Invoice::CATEGORIES as $key => $label) {
            $summary["Billed for $label"] = self::money($items->where('category', $key)->sum('amount'));
        }

        $days = collect($invoices->groupBy(fn ($i) => $i->invoice_date->format('Y-m-d')))->map(fn ($g) => ['billed' => $g->sum('total'), 'collected' => 0.0]);

        foreach ($payments->groupBy(fn ($p) => $p->paid_at->format('Y-m-d')) as $date => $group) {
            $days[$date] = ['billed' => $days[$date]['billed'] ?? 0.0, 'collected' => (float) $group->sum('amount')];
        }

        $rows = $days->sortKeys()->map(fn ($v, $date) => [Carbon::parse($date)->format('d M Y, D'), self::money($v['billed']), self::money($v['collected'])])->values()->all();

        return ['title' => 'Revenue report', 'summary' => $summary, 'headings' => ['Date', 'Billed', 'Collected'], 'rows' => $rows];
    }

    protected static function pending(int $doctorId): array
    {
        $invoices = Invoice::forDoctor($doctorId)->owing()->with('patient')->orderBy('invoice_date')->get();

        return [
            'title' => 'Pending payments',
            'summary' => [
                'Invoices pending' => $invoices->count(),
                'Total pending' => self::money($invoices->sum(fn ($i) => $i->due())),
                'Pending for more than 30 days' => $invoices->filter(fn ($i) => $i->invoice_date->lt(today()->subDays(30)))->count(),
            ],
            'headings' => ['Invoice', 'Patient', 'Phone', 'Date', 'Total', 'Paid', 'Due', 'Days pending'],
            'rows' => $invoices->map(fn ($i) => [$i->number, $i->patient->name, $i->patient->phone, $i->invoice_date->format('d M Y'), self::money($i->total), self::money($i->paid_amount), self::money($i->due()), (int) $i->invoice_date->diffInDays(today())])->all(),
        ];
    }

    protected static function staff(Carbon $from, Carbon $to, int $doctorId): array
    {
        $members = Staff::forDoctor($doctorId)->with('role')->orderBy('name')->get();

        $attendance = StaffAttendance::whereIn('staff_id', $members->pluck('id'))->whereDate('work_date', '>=', $from->toDateString())->whereDate('work_date', '<=', $to->toDateString())->get()->groupBy('staff_id');
        $tasks = StaffTask::whereIn('staff_id', $members->pluck('id'))->get()->groupBy('staff_id');
        $payments = StaffPayment::whereIn('staff_id', $members->pluck('id'))->whereDate('paid_on', '>=', $from->toDateString())->whereDate('paid_on', '<=', $to->toDateString())->get()->groupBy('staff_id');

        $rows = $members->map(function ($m) use ($attendance, $tasks, $payments) {
            $a = $attendance->get($m->id, collect());
            $t = $tasks->get($m->id, collect());

            return [
                $m->name, $m->role?->name ?? '', $a->where('status', 'present')->count(), $a->where('status', 'half_day')->count(), $a->where('status', 'leave')->count(),
                $a->where('status', 'absent')->count(), $t->where('status', 'done')->count(), $t->where('status', 'pending')->count(), self::money($payments->get($m->id, collect())->sum('amount')),
            ];
        })->all();

        return [
            'title' => 'Staff report',
            'summary' => ['Staff members' => $members->count(), 'Active' => $members->where('is_active', true)->count(), 'Paid in this period' => self::money($payments->flatten()->sum('amount'))],
            'headings' => ['Name', 'Role', 'Present', 'Half days', 'Leave', 'Absent', 'Tasks done', 'Tasks open', 'Paid'],
            'rows' => $rows,
        ];
    }

    /** Writes a report as CSV. Cells that start with a formula character are neutralised so spreadsheets do not run them. */
    public static function toCsv(array $report, $handle): void
    {
        $safe = fn ($v) => is_string($v) && $v !== '' && in_array($v[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'".$v : $v;

        fputcsv($handle, [$report['title']]);

        foreach ($report['summary'] as $label => $value) {
            fputcsv($handle, [$label, $safe((string) $value)]);
        }

        fputcsv($handle, []);
        fputcsv($handle, $report['headings']);

        foreach ($report['rows'] as $row) {
            fputcsv($handle, array_map($safe, $row));
        }
    }
}
