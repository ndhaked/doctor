<?php

namespace Database\Seeders;

use App\Models\Admin;
use App\Models\Appointment;
use App\Models\Consultation;
use App\Models\ConsultationField;
use App\Models\Enquiry;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\Staff;
use App\Models\StaffRole;
use App\Support\ClinicContext;
use App\Support\ClinicSettings;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * Fills the clinic panel with made-up records so every screen can be tried with data.
 *
 * Demo patients, staff and enquiries are tagged "DEMO" so `php artisan clinic:clear-demo` can remove exactly
 * these records later. It does nothing when the clinic already has patients, so real data is never mixed in.
 */
class DemoClinicSeeder extends Seeder
{
    public const TAG = 'DEMO';

    /** Demo logins, one per role, all with the password "password". */
    public const LOGINS = ['doctor@doctorweb.test' => 'Doctor', 'reception@doctorweb.test' => 'Receptionist', 'nurse@doctorweb.test' => 'Nurse'];

    protected array $slotsUsed = [];

    public function run(): void
    {
        $this->call([SpecializationSeeder::class, StaffRoleSeeder::class]);

        $doctor = ClinicContext::doctor();

        if (Patient::forDoctor($doctor->id)->exists()) {
            $this->command?->warn('The clinic already has patients, so no demo data was added.');

            return;
        }

        $doctor->update([
            'specialization' => $doctor->specialization ?: 'General Physician',
            'registration_no' => $doctor->registration_no ?: 'RMC-2015-48213',
            'consultation_fee' => (float) $doctor->consultation_fee > 0 ? $doctor->consultation_fee : 500,
            'qualification' => $doctor->qualification ?: 'MBBS, MD (Internal Medicine)',
        ]);

        $fields = $this->customFields($doctor->id);
        $patients = $this->patients($doctor->id);

        $this->history($doctor, $patients, $fields);
        $this->documents($patients);
        $this->today($doctor, $patients);
        $this->upcoming($doctor, $patients);
        $this->staff($doctor->id, $patients);
        $this->enquiries();
        $this->logins($doctor);

        $this->command?->info('Demo data added: '.count($patients).' patients with appointments, consultations, invoices, staff and enquiries.');
    }

    /* ---------- building blocks ---------- */

    protected function customFields(int $doctorId): array
    {
        if (! ConsultationField::where('doctor_id', $doctorId)->exists()) {
            foreach ([
                ['Blood sugar (mg/dL)', 'number', null],
                ['Family history', 'textarea', null],
                ['Smoker', 'checkbox', null],
                ['Diet type', 'select', "Vegetarian\nNon-vegetarian\nVegan"],
            ] as $i => [$label, $type, $options]) {
                ConsultationField::create(['doctor_id' => $doctorId, 'label' => $label, 'type' => $type, 'options' => $options, 'sort_order' => $i + 1]);
            }
        }

        return ConsultationField::where('doctor_id', $doctorId)->get()->keyBy('label')->all();
    }

    protected function patients(int $doctorId): array
    {
        $people = [
            ['Aarav Mehta', 'male', 34, 'Penicillin allergy', 'Type 2 diabetes since 2019, on Metformin'],
            ['Diya Sharma', 'female', 28, null, null],
            ['Rohan Gupta', 'male', 45, null, 'Hypertension, on Amlodipine'],
            ['Ananya Iyer', 'female', 31, 'Dust, pollen', 'Asthma since childhood'],
            ['Vikram Singh', 'male', 52, null, 'Knee osteoarthritis'],
            ['Priya Nair', 'female', 39, 'Sulfa drugs', null],
            ['Kabir Khan', 'male', 8, null, null],
            ['Meera Joshi', 'female', 62, 'Aspirin', 'Hypothyroidism, on Thyroxine'],
            ['Arjun Reddy', 'male', 27, null, null],
            ['Sana Ali', 'female', 24, null, null],
            ['Harish Patel', 'male', 58, null, 'Ischaemic heart disease, stent in 2021'],
            ['Neha Verma', 'female', 36, null, 'Migraine'],
            ['Yash Agarwal', 'male', 12, 'Peanuts', null],
            ['Kavita Rao', 'female', 47, null, 'PCOD'],
            ['Suresh Kumar', 'male', 67, null, 'COPD, ex-smoker'],
            ['Isha Kapoor', 'female', 29, null, null],
            ['Manoj Tiwari', 'male', 41, null, null],
            ['Pooja Desai', 'female', 33, 'Latex', null],
            ['Rahul Bansal', 'male', 49, null, 'High cholesterol'],
            ['Lata Menon', 'female', 71, null, 'Type 2 diabetes, hypertension'],
            ['Dev Malhotra', 'male', 19, null, null],
            ['Sneha Pillai', 'female', 26, null, null],
            ['Amit Saxena', 'male', 38, null, null],
            ['Zoya Sheikh', 'female', 5, null, null],
        ];

        $groups = ['A+', 'B+', 'O+', 'AB+', 'A-', 'O-', 'B-'];
        $out = [];

        foreach ($people as $i => [$name, $gender, $age, $allergy, $history]) {
            $out[] = Patient::register([
                'doctor_id' => $doctorId,
                'name' => $name,
                'gender' => $gender,
                'dob' => now()->subYears($age)->subDays(($i * 37) % 300)->format('Y-m-d'),
                'phone' => '98'.str_pad((string) (10000000 + $i * 137531), 8, '0', STR_PAD_LEFT),
                'email' => strtolower(str_replace(' ', '.', $name)).'@example.com',
                'address' => (12 + $i).', Demo Colony, Sample City',
                'blood_group' => $groups[$i % count($groups)],
                'emergency_contact' => 'Family member '.(9000000000 + $i * 1111),
                'allergies' => $allergy,
                'medical_history' => $history,
                'notes' => self::TAG,
                'created_at' => now()->subDays(75 - $i * 3),
            ]);
        }

        return $out;
    }

    /** The next free 15-minute slot of a day (09:00 onwards), so demo appointments never overlap. */
    protected function slot(Carbon $day): Carbon
    {
        $key = $day->format('Y-m-d');
        $n = $this->slotsUsed[$key] = ($this->slotsUsed[$key] ?? -1) + 1;

        return $day->copy()->setTime(9, 0)->addMinutes($n * 15);
    }

    /** The last open day on or before the given number of days ago. */
    protected function workingDay(int $daysAgo, $doctor): Carbon
    {
        $day = today()->subDays($daysAgo);

        while (! $doctor->isOpenOn($day)) {
            $day->subDay();
        }

        return $day;
    }

    protected function history($doctor, array $patients, array $fields): void
    {
        $cases = [
            ['Fever and body ache for 3 days', 'Viral fever', 'Rest, plenty of fluids', [['Paracetamol', '650 mg', '1-1-1', '3 days', 'After food'], ['ORS sachet', '1 sachet', 'Twice daily', '3 days', 'In 1 litre water']], 'Complete blood count', ['bp' => '118/76', 'pulse' => '92', 'temperature' => '100.4', 'spo2' => '98']],
            ['Cough and cold for a week', 'Upper respiratory tract infection', 'Steam inhalation, warm fluids', [['Cetirizine', '10 mg', '0-0-1', '5 days', 'At night'], ['Ambroxol syrup', '10 ml', '1-0-1', '5 days', null]], null, ['bp' => '122/80', 'pulse' => '84', 'temperature' => '98.6', 'spo2' => '97']],
            ['Regular check-up, sugar review', 'Type 2 diabetes mellitus, controlled', 'Continue current medicines, diet control', [['Metformin', '500 mg', '1-0-1', '30 days', 'After meals'], ['Glimepiride', '1 mg', '1-0-0', '30 days', 'Before breakfast']], 'HbA1c, fasting sugar, lipid profile', ['bp' => '130/84', 'pulse' => '78', 'weight' => '78', 'height' => '172']],
            ['Headache and dizziness', 'Hypertension, stage 1', 'Low salt diet, daily walk', [['Amlodipine', '5 mg', '1-0-0', '30 days', null]], 'Kidney function test, ECG', ['bp' => '152/96', 'pulse' => '88', 'weight' => '82']],
            ['Acidity and stomach pain', 'Gastritis', 'Avoid spicy and oily food', [['Pantoprazole', '40 mg', '1-0-0', '14 days', 'Before breakfast'], ['Antacid gel', '10 ml', '1-1-1', '7 days', 'After meals']], null, ['bp' => '120/78', 'pulse' => '80']],
            ['Joint pain in both knees', 'Osteoarthritis of the knee', 'Physiotherapy exercises, weight control', [['Diclofenac gel', 'Apply', 'Twice daily', '10 days', 'Local application'], ['Calcium + Vitamin D3', '1 tablet', '0-0-1', '60 days', null]], 'X-ray both knees', ['bp' => '134/86', 'pulse' => '82', 'weight' => '88']],
        ];

        $order = 0;

        foreach (array_slice($patients, 0, 18) as $pIndex => $patient) {
            $visits = 1 + ($pIndex % 3);

            for ($v = 0; $v < $visits; $v++) {
                $case = $cases[($pIndex + $v) % count($cases)];
                $day = $this->workingDay(5 + $pIndex * 3 + $v * 20, $doctor);
                $when = $this->slot($day);

                $appointment = Appointment::create([
                    'doctor_id' => $doctor->id, 'patient_id' => $patient->id, 'scheduled_at' => $when, 'duration_minutes' => 15,
                    'type' => $v === 0 ? 'new' : 'follow_up', 'status' => 'completed', 'token_no' => 1 + ($this->slotsUsed[$day->format('Y-m-d')] ?? 0),
                    'checked_in_at' => $when->copy()->subMinutes(10), 'reason' => $case[0],
                ]);

                // Follow-ups: some already overdue (to show on the dashboard), some still ahead
                $followUp = match (true) {
                    $v === $visits - 1 && $pIndex % 4 === 0 => today()->subDays(2 + $pIndex % 5),
                    $v === $visits - 1 && $pIndex % 4 === 1 => today()->addDays(3 + $pIndex % 6),
                    default => null,
                };

                $custom = [];
                foreach ($fields as $label => $field) {
                    $custom[$field->id] = match ($label) {
                        'Blood sugar (mg/dL)' => (string) (95 + (($pIndex * 17 + $v * 11) % 90)),
                        'Family history' => $pIndex % 2 ? 'Father: diabetes' : 'No significant history',
                        'Smoker' => $pIndex % 5 === 0,
                        'Diet type' => ['Vegetarian', 'Non-vegetarian', 'Vegan'][$pIndex % 3],
                        default => null,
                    };
                }

                $consultation = Consultation::create([
                    'doctor_id' => $doctor->id, 'patient_id' => $patient->id, 'appointment_id' => $appointment->id,
                    'consulted_at' => $when->copy()->addMinutes(5),
                    'chief_complaint' => $case[0], 'symptoms' => $case[0], 'vitals' => $case[5], 'examination' => 'General condition fair. Systemic examination normal.',
                    'diagnosis' => $case[1], 'treatment' => $case[2], 'notes' => 'Demo record', 'custom_values' => $custom,
                    'tests' => $case[4], 'advice' => 'Take medicines on time. Review if symptoms persist.', 'follow_up_date' => $followUp, 'status' => 'final',
                ]);

                foreach ($case[3] as $i => [$medicine, $dosage, $frequency, $duration, $instructions]) {
                    $consultation->items()->create(['medicine' => $medicine, 'dosage' => $dosage, 'frequency' => $frequency, 'duration' => $duration, 'instructions' => $instructions, 'sort_order' => $i + 1]);
                }

                $this->invoice($doctor, $patient, $consultation, $when, $order++);
            }
        }

        // One cancelled and one no-show appointment in the past
        foreach ([[18, 'cancelled', 'Patient called to cancel'], [19, 'no_show', null]] as [$index, $status, $reason]) {
            Appointment::create([
                'doctor_id' => $doctor->id, 'patient_id' => $patients[$index]->id, 'scheduled_at' => $this->slot($this->workingDay(2 + $index % 3, $doctor)),
                'duration_minutes' => 15, 'status' => $status, 'cancel_reason' => $reason, 'reason' => 'Check-up',
            ]);
        }
    }

    /** Alternates between fully paid, part paid and unpaid so the billing screens and pending reports have something to show. */
    protected function invoice($doctor, Patient $patient, Consultation $consultation, Carbon $when, int $order): void
    {
        $invoice = Invoice::create([
            'doctor_id' => $doctor->id, 'patient_id' => $patient->id, 'consultation_id' => $consultation->id, 'appointment_id' => $consultation->appointment_id,
            'number' => 'TMP-'.bin2hex(random_bytes(6)), 'invoice_date' => $when->format('Y-m-d'), 'discount' => $order % 6 === 0 ? 50 : 0, 'notes' => 'Demo invoice',
        ]);
        $invoice->update(['number' => ClinicSettings::content()['invoice_prefix'].'-'.$when->format('Y').'-'.str_pad((string) $invoice->id, 5, '0', STR_PAD_LEFT)]);

        $lines = [['consultation', 'Consultation fee', 1, (float) $doctor->consultation_fee]];

        if ($order % 3 === 0) {
            $lines[] = ['lab', 'Blood tests', 1, 650];
        }

        if ($order % 4 === 1) {
            $lines[] = ['procedure', 'Dressing / injection', 1, 200];
        }

        foreach ($lines as $i => [$category, $description, $qty, $price]) {
            $invoice->items()->create(['category' => $category, 'description' => $description, 'quantity' => $qty, 'unit_price' => $price, 'amount' => $qty * $price, 'sort_order' => $i + 1]);
        }

        $invoice->recalculate();

        $methods = ['cash', 'upi', 'card', 'bank'];
        $mode = $order % 3; // 0 = paid, 1 = part paid, 2 = unpaid
        $amount = $mode === 0 ? (float) $invoice->total : ($mode === 1 ? round((float) $invoice->total / 2, 2) : 0);

        if ($amount > 0) {
            $payment = $invoice->payments()->create([
                'patient_id' => $patient->id, 'receipt_number' => 'TMP-'.bin2hex(random_bytes(6)), 'amount' => $amount,
                'method' => $methods[$order % 4], 'paid_at' => $when->copy()->addMinutes(30), 'reference' => $order % 4 === 1 ? 'UPI'.(100000 + $order) : null,
            ]);
            $payment->update(['receipt_number' => 'RCP-'.str_pad((string) $payment->id, 6, '0', STR_PAD_LEFT)]);
            $invoice->recalculate();
        }
    }

    /** Sample reports for the first patients, kept on the private disk like real uploads. */
    protected function documents(array $patients): void
    {
        $titles = ['Blood test report', 'X-ray report', 'Discharge summary', 'ECG report', 'Ultrasound report', 'Lipid profile'];

        foreach (array_slice($patients, 0, 8) as $i => $patient) {
            $title = $titles[$i % count($titles)];
            $path = "patient-documents/{$patient->id}/demo-".($i + 1).'.pdf';

            Storage::disk('local')->put($path, $this->tinyPdf($title.' - '.$patient->name));
            $patient->documents()->create(['title' => $title.' (demo)', 'file' => $path]);
        }
    }

    /** A one-page PDF with a line of text, so the document viewer has something real to open. */
    protected function tinyPdf(string $text): string
    {
        $text = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
        $stream = "BT /F1 18 Tf 60 760 Td ($text) Tj ET";

        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>',
            '<< /Length '.strlen($stream)." >>\nstream\n$stream\nendstream",
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [];

        foreach ($objects as $i => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($i + 1)." 0 obj\n$object\nendobj\n";
        }

        $xref = strlen($pdf);
        $pdf .= 'xref
0 '.(count($objects) + 1)."\n0000000000 65535 f \n";

        foreach ($offsets as $offset) {
            $pdf .= str_pad((string) $offset, 10, '0', STR_PAD_LEFT)." 00000 n \n";
        }

        return $pdf.'trailer
<< /Size '.(count($objects) + 1).' /Root 1 0 R >>
startxref
'.$xref."\n%%EOF";
    }

    protected function today($doctor, array $patients): void
    {
        $day = today();

        if (! $doctor->isOpenOn($day)) {
            return; // nothing to book on the doctor's day off
        }

        $line = [
            [18, 'completed', true], [19, 'in_consultation', true], [20, 'checked_in', false], [21, 'checked_in', false], [22, 'scheduled', false], [23, 'scheduled', false],
        ];

        $token = 0;

        foreach ($line as $i => [$index, $status, $withConsultation]) {
            $when = $this->slot($day);
            $arrived = in_array($status, ['completed', 'in_consultation', 'checked_in'], true);

            $appointment = Appointment::create([
                'doctor_id' => $doctor->id, 'patient_id' => $patients[$index]->id, 'scheduled_at' => $when, 'duration_minutes' => 15, 'type' => $i % 3 === 0 ? 'follow_up' : 'new',
                'status' => $status, 'token_no' => $arrived ? ++$token : null, 'checked_in_at' => $arrived ? $when->copy()->subMinutes(10) : null,
                'reason' => ['Routine check-up', 'Fever since yesterday', 'Follow-up visit', 'Cough and cold', 'Blood pressure check', 'Vaccination advice'][$i],
            ]);

            if ($withConsultation) {
                Consultation::create([
                    'doctor_id' => $doctor->id, 'patient_id' => $patients[$index]->id, 'appointment_id' => $appointment->id, 'consulted_at' => $when->copy()->addMinutes(5),
                    'chief_complaint' => $appointment->reason, 'diagnosis' => $status === 'completed' ? 'Seasonal viral infection' : null,
                    'status' => $status === 'completed' ? 'final' : 'draft', 'vitals' => ['bp' => '120/80', 'pulse' => '82'],
                ]);
            }
        }
    }

    protected function upcoming($doctor, array $patients): void
    {
        foreach ([1, 1, 2, 2, 3, 4] as $i => $offset) {
            $day = today()->addDays($offset);

            while (! $doctor->isOpenOn($day)) {
                $day->addDay();
            }

            Appointment::create([
                'doctor_id' => $doctor->id, 'patient_id' => $patients[($i * 3 + 1) % count($patients)]->id, 'scheduled_at' => $this->slot($day), 'duration_minutes' => 15,
                'type' => $i % 2 ? 'follow_up' : 'new', 'status' => 'scheduled', 'reason' => ['Review of reports', 'Follow-up', 'New complaint', 'Diabetes check', 'BP monitoring', 'General check-up'][$i],
            ]);
        }
    }

    protected function staff(int $doctorId, array $patients): void
    {
        $roles = StaffRole::pluck('id', 'name');

        $members = [
            ['Sunita Verma', 'Nurse', 18000], ['Ramesh Yadav', 'Compounder', 14000], ['Geeta Kumari', 'Receptionist', 15000],
            ['Mohan Lal', 'Attendant', 11000], ['Farhan Qureshi', 'Technician', 16000],
        ];

        foreach ($members as $i => [$name, $role, $salary]) {
            $staff = Staff::create([
                'doctor_id' => $doctorId, 'staff_role_id' => $roles[$role] ?? null, 'name' => $name, 'phone' => '97'.str_pad((string) (20000000 + $i * 91357), 8, '0', STR_PAD_LEFT),
                'joined_on' => now()->subMonths(6 + $i * 5)->format('Y-m-d'), 'monthly_salary' => $salary, 'notes' => self::TAG,
            ]);

            // Attendance for the last 14 days (Sundays off)
            for ($d = 13; $d >= 0; $d--) {
                $date = today()->subDays($d);

                if ($date->isSunday()) {
                    continue;
                }

                $status = (($d + $i) % 9 === 0) ? 'absent' : ((($d + $i) % 7 === 0) ? 'leave' : 'present');
                $staff->attendance()->create([
                    'work_date' => $date->format('Y-m-d'), 'status' => $status,
                    'check_in' => $status === 'present' ? '08:'.str_pad((string) (45 + ($d + $i) % 15), 2, '0', STR_PAD_LEFT) : null,
                    'check_out' => $status === 'present' ? '17:30' : null,
                ]);
            }

            for ($d = 0; $d < 5; $d++) {
                $staff->shifts()->create(['shift_date' => today()->addDays($d)->format('Y-m-d'), 'starts_at' => $i % 2 ? '14:00' : '09:00', 'ends_at' => $i % 2 ? '20:00' : '15:00', 'note' => $i % 2 ? 'Evening shift' : 'Morning shift']);
            }

            foreach ([-1, 0] as $m) {
                $month = today()->startOfMonth()->addMonths($m);

                if ($m === 0 && $i % 2) {
                    continue; // current month not paid yet for some staff
                }

                $staff->payments()->create(['type' => 'salary', 'period' => $month->format('Y-m'), 'amount' => $salary, 'paid_on' => $month->copy()->addDays(27)->min(today())->format('Y-m-d'), 'method' => 'bank', 'note' => 'Demo payment']);
            }

            foreach (array_slice($patients, $i * 2, 2) as $patient) {
                $staff->patients()->syncWithoutDetaching([$patient->id => ['note' => 'Daily care']]);
                $staff->tasks()->create(['patient_id' => $patient->id, 'title' => 'Check vitals of '.$patient->name, 'due_date' => today()->addDays($i % 3)->format('Y-m-d'), 'details' => 'Record BP, pulse and temperature']);
            }

            $staff->tasks()->create(['title' => 'Restock consumables', 'due_date' => today()->subDay()->format('Y-m-d')]);
            $staff->tasks()->create(['title' => 'Sterilise instruments', 'status' => 'done', 'completed_at' => now()->subHours(3), 'due_date' => today()->format('Y-m-d')]);
        }
    }

    protected function logins($doctor): void
    {
        foreach (self::LOGINS as $email => $role) {
            $admin = Admin::updateOrCreate(['email' => $email], ['name' => 'Demo '.$role, 'password' => 'password']);
            $admin->syncRoles([$role]);

            // The doctor login works on the doctor's own profile
            if ($role === 'Doctor' && ! $doctor->admin_id) {
                $doctor->update(['admin_id' => $admin->id]);
            }
        }
    }

    protected function enquiries(): void
    {
        $rows = [
            ['appointment', 'Rakesh Jain', '9812345678', 'rakesh@example.com', 'I would like an appointment this week for a general check-up.', 'new'],
            ['popup', 'Sunita Rawat', '9823456789', null, 'Please call me back about diabetes consultation timings.', 'new'],
            ['contact', 'Deepak Sinha', '9834567890', 'deepak@example.com', 'Do you accept online payments and give e-prescriptions?', 'new'],
            ['strip', 'Alka Mishra', '9845678901', null, null, 'read'],
            ['appointment', 'Tarun Bhatia', '9856789012', 'tarun@example.com', 'Need a follow-up appointment for my father.', 'read'],
        ];

        foreach ($rows as $i => [$source, $name, $phone, $email, $message, $status]) {
            Enquiry::create([
                'source' => $source, 'name' => $name, 'phone' => $phone, 'email' => $email, 'message' => $message, 'consent' => true,
                'status' => $status, 'ip' => '127.0.0.1', 'user_agent' => self::TAG, 'created_at' => now()->subHours(2 + $i * 7),
            ]);
        }
    }
}
