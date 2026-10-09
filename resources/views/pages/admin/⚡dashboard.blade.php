<?php

use App\Models\Appointment;
use App\Models\Consultation;
use App\Models\Enquiry;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\Payment;
use App\Support\ClinicContext;
use App\Support\ClinicSettings;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::admin')] class extends Component
{
    protected function allowed(string $permission): bool
    {
        return auth('admin')->user()->can($permission);
    }

    #[Computed]
    public function showClinic(): bool
    {
        return $this->allowed('view clinic dashboard');
    }

    /** Only the numbers this person may see are calculated. */
    #[Computed]
    public function stats(): array
    {
        $doctorId = ClinicContext::id();
        $stats = [];

        if ($this->allowed('manage patients')) {
            $stats['patients'] = Patient::forDoctor($doctorId)->count();
        }

        if ($this->allowed('manage appointments')) {
            $stats['appointments'] = Appointment::forDoctor($doctorId)->onDate(today())->whereNotIn('status', ['cancelled', 'no_show'])->count();
            $stats['waiting'] = Appointment::forDoctor($doctorId)->onDate(today())->where('status', 'checked_in')->count();
        }

        if ($this->allowed('view medical records')) {
            $stats['consultations'] = Consultation::forDoctor($doctorId)->whereDate('consulted_at', today())->count();
            $stats['followups'] = $this->pendingFollowups()->count();
        }

        if ($this->allowed('manage billing')) {
            $payments = Payment::whereHas('invoice', fn ($q) => $q->forDoctor($doctorId));
            $stats['pending_amount'] = round((float) Invoice::forDoctor($doctorId)->owing()->get()->sum(fn ($i) => $i->due()), 2);
            $stats['pending_invoices'] = Invoice::forDoctor($doctorId)->owing()->count();
            $stats['revenue_today'] = round((float) (clone $payments)->whereDate('paid_at', today())->sum('amount'), 2);
            $stats['revenue_month'] = round((float) (clone $payments)->whereBetween('paid_at', [now()->startOfMonth(), now()->endOfMonth()])->sum('amount'), 2);
        }

        return $stats;
    }

    /** Follow-ups that are due (or overdue) and have no later visit booked or done. */
    protected function pendingFollowups()
    {
        return Consultation::forDoctor(ClinicContext::id())
            ->whereNotNull('follow_up_date')
            ->whereDate('follow_up_date', '<=', today())
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))->from('appointments')
                    ->whereColumn('appointments.patient_id', 'consultations.patient_id')
                    ->whereIn('appointments.status', ['scheduled', 'checked_in', 'in_consultation', 'completed'])
                    ->whereColumn('appointments.scheduled_at', '>', 'consultations.consulted_at');
            });
    }

    #[Computed]
    public function followups()
    {
        return $this->allowed('view medical records') ? $this->pendingFollowups()->with('patient')->orderBy('follow_up_date')->limit(6)->get() : collect();
    }

    #[Computed]
    public function todaySchedule()
    {
        return $this->allowed('manage appointments')
            ? Appointment::forDoctor(ClinicContext::id())->onDate(today())->whereNotIn('status', ['cancelled', 'no_show'])->with('patient')->orderBy('scheduled_at')->limit(8)->get()
            : collect();
    }

    #[Computed]
    public function unreadEnquiries(): int
    {
        return $this->allowed('manage enquiries') ? Enquiry::unread()->count() : 0;
    }
}
?>

<div>
    <x-slot:header>
        <h2 class="text-lg font-semibold text-gray-900">Dashboard</h2>
    </x-slot:header>

    @php
        $stats = $this->showClinic ? $this->stats : [];
        $money = fn ($v) => \App\Support\ClinicSettings::money($v);
        $cards = [];

        if (isset($stats['patients'])) $cards[] = ['Total patients', $stats['patients'], 'users', 'amber', route('admin.clinic.patients.index')];
        if (isset($stats['appointments'])) $cards[] = ["Today's appointments", $stats['appointments'], 'calendar', 'emerald', route('admin.clinic.appointments.index'), $stats['waiting'].' waiting'];
        if (isset($stats['consultations'])) $cards[] = ["Today's consultations", $stats['consultations'], 'clipboard', 'sky', route('admin.clinic.consultations.index')];
        if (isset($stats['followups'])) $cards[] = ['Pending follow-ups', $stats['followups'], 'badge-check', 'indigo', route('admin.clinic.consultations.index', ['filter' => 'followup'])];
        if (isset($stats['pending_amount'])) {
            $cards[] = ['Pending payments', $money($stats['pending_amount']), 'banknotes', 'red', route('admin.clinic.billing.index', ['filter' => 'owing']), $stats['pending_invoices'].' invoices'];
            $cards[] = ['Revenue today', $money($stats['revenue_today']), 'banknotes', 'green', route('admin.clinic.billing.index')];
            $cards[] = ['Revenue this month', $money($stats['revenue_month']), 'banknotes', 'green', route('admin.clinic.billing.index')];
        }

        $tones = ['amber' => 'bg-amber-50 text-amber-600', 'emerald' => 'bg-emerald-50 text-emerald-600', 'sky' => 'bg-sky-50 text-sky-600', 'indigo' => 'bg-indigo-50 text-indigo-600', 'red' => 'bg-red-50 text-red-600', 'green' => 'bg-green-50 text-green-600'];
    @endphp

    <div class="mb-6 rounded-xl border border-gray-200 bg-white p-5">
        <p class="text-sm text-gray-600">Welcome back, <span class="font-medium text-gray-900">{{ auth('admin')->user()->name }}</span>.
            @if ($this->unreadEnquiries > 0)
                You have <a href="{{ route('admin.enquiries.index') }}" wire:navigate class="font-medium text-indigo-600 hover:text-indigo-800">{{ $this->unreadEnquiries }} unread website {{ \Illuminate\Support\Str::plural('enquiry', $this->unreadEnquiries) }}</a>.
            @endif
        </p>
    </div>

    @if ($cards)
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @foreach (array_map(fn ($c) => array_pad($c, 6, null), $cards) as [$label, $value, $icon, $tone, $url, $sub])
                <a href="{{ $url }}" wire:navigate wire:key="card-{{ $loop->index }}" class="flex items-center gap-4 rounded-xl border border-gray-200 bg-white p-5 transition hover:border-indigo-200 hover:shadow-sm">
                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg {{ $tones[$tone] }}"><x-icon :name="$icon" class="h-6 w-6" /></span>
                    <div>
                        <p class="text-sm text-gray-500">{{ $label }}</p>
                        <p class="text-2xl font-semibold text-gray-900">{{ $value }}</p>
                        @if (! empty($sub)) <p class="text-xs text-gray-400">{{ $sub }}</p> @endif
                    </div>
                </a>
            @endforeach
        </div>

        <div class="mt-6 grid gap-6 lg:grid-cols-2">
            @if (auth('admin')->user()->can('manage appointments'))
                <section class="rounded-xl border border-gray-200 bg-white">
                    <div class="flex items-center justify-between border-b border-gray-200 px-4 py-3">
                        <h3 class="text-sm font-semibold text-gray-900">Today&rsquo;s schedule</h3>
                        <a href="{{ route('admin.clinic.appointments.index') }}" wire:navigate class="text-xs font-medium text-indigo-600 hover:text-indigo-800">Open appointments</a>
                    </div>
                    <ul class="divide-y divide-gray-100 text-sm">
                        @forelse ($this->todaySchedule as $a)
                            <li wire:key="today-{{ $a->id }}" class="flex items-center justify-between gap-3 px-4 py-2.5">
                                <span><span class="inline-block w-20 font-semibold text-gray-800">{{ $a->scheduled_at->format('h:i A') }}</span> {{ $a->patient->name }}</span>
                                <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $a->badgeClasses() }}">{{ $a->statusLabel() }}</span>
                            </li>
                        @empty
                            <li class="px-4 py-8 text-center text-gray-400">No appointments today.</li>
                        @endforelse
                    </ul>
                </section>
            @endif

            @if (auth('admin')->user()->can('view medical records'))
                <section class="rounded-xl border border-gray-200 bg-white">
                    <div class="flex items-center justify-between border-b border-gray-200 px-4 py-3">
                        <h3 class="text-sm font-semibold text-gray-900">Follow-ups to arrange</h3>
                        <a href="{{ route('admin.clinic.consultations.index', ['filter' => 'followup']) }}" wire:navigate class="text-xs font-medium text-indigo-600 hover:text-indigo-800">See all</a>
                    </div>
                    <ul class="divide-y divide-gray-100 text-sm">
                        @forelse ($this->followups as $f)
                            <li wire:key="fu-{{ $f->id }}" class="flex items-center justify-between gap-3 px-4 py-2.5">
                                <a href="{{ route('admin.clinic.patients.show', $f->patient_id) }}" wire:navigate class="font-medium text-gray-900 hover:text-indigo-700">{{ $f->patient->name }}</a>
                                <span class="text-xs {{ $f->follow_up_date->isToday() ? 'text-sky-600' : 'text-red-600' }}">{{ $f->follow_up_date->isToday() ? 'Due today' : 'Overdue since '.$f->follow_up_date->format('d M') }}</span>
                            </li>
                        @empty
                            <li class="px-4 py-8 text-center text-gray-400">No pending follow-ups.</li>
                        @endforelse
                    </ul>
                </section>
            @endif
        </div>
    @else
        <div class="rounded-xl border border-gray-200 bg-white p-6">
            <p class="text-sm text-gray-600">Use the menu on the left to manage {{ config('app.name') }}.</p>
        </div>
    @endif
</div>
