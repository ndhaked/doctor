<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Appointment;
use App\Models\Consultation;
use App\Models\Doctor;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\Staff;
use App\Models\StaffRole;
use App\Support\ClinicContext;
use App\Support\ClinicReports;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ClinicStaffReportsTest extends TestCase
{
    use RefreshDatabase;

    protected function loginAs(string $role): Admin
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $admin = Admin::factory()->create();
        $admin->assignRole($role);
        $this->actingAs($admin, 'admin');

        return $admin;
    }

    protected function doctorId(): int
    {
        return ClinicContext::id();
    }

    protected function patient(string $name = 'Ravi Kumar'): Patient
    {
        return Patient::register(['doctor_id' => $this->doctorId(), 'name' => $name, 'phone' => '9876543210', 'gender' => 'male']);
    }

    protected function staff(string $name = 'Nurse Anita'): Staff
    {
        return Staff::create(['doctor_id' => $this->doctorId(), 'name' => $name, 'monthly_salary' => 12000]);
    }

    /* ---------- staff ---------- */

    public function test_only_staff_managers_open_the_staff_pages(): void
    {
        $this->loginAs('Receptionist');
        $this->get(route('admin.clinic.staff.index'))->assertForbidden();

        $this->loginAs('Doctor');
        $this->get(route('admin.clinic.staff.index'))->assertOk();
    }

    public function test_staff_are_added_edited_deactivated_and_deleted(): void
    {
        $this->loginAs('Doctor');
        $this->seed(\Database\Seeders\StaffRoleSeeder::class);
        $role = StaffRole::where('name', 'Nurse')->first();

        $c = Livewire::test('pages::admin.clinic.staff.index')
            ->call('create')->call('save')->assertHasErrors(['name'])
            ->set('name', 'Anita Joshi')->set('staff_role_id', $role->id)->set('phone', 'abc')->call('save')->assertHasErrors(['phone'])
            ->set('phone', '9000011111')->set('monthly_salary', '15000')->call('save')->assertHasNoErrors();

        $member = Staff::first();
        $this->assertSame('Anita Joshi', $member->name);
        $this->assertSame($role->id, $member->staff_role_id);

        $c->call('edit', $member->id)->set('name', 'Anita J.')->call('save')->call('toggle', $member->id);
        $this->assertSame('Anita J.', $member->fresh()->name);
        $this->assertFalse($member->fresh()->is_active);

        $c->call('delete', $member->id);
        $this->assertSame(0, Staff::count());
    }

    public function test_staff_of_another_doctor_is_not_reachable(): void
    {
        $this->loginAs('Doctor');
        $this->doctorId();
        $other = Doctor::create(['name' => 'Dr. Other']);
        $foreign = Staff::create(['doctor_id' => $other->id, 'name' => 'Foreign Staff']);

        $this->get(route('admin.clinic.staff.show', $foreign))->assertNotFound();
        Livewire::test('pages::admin.clinic.staff.index')->assertDontSee('Foreign Staff');
    }

    public function test_patients_can_be_assigned_to_staff_and_given_tasks(): void
    {
        $this->loginAs('Doctor');
        $staff = $this->staff();
        $patient = $this->patient();

        $c = Livewire::test('pages::admin.clinic.staff.show', ['staff' => $staff])
            ->set('patient_search', 'Ravi')->call('assignPatient', $patient->id);

        $this->assertTrue($staff->patients()->whereKey($patient->id)->exists());

        $c->set('task_title', '')->call('addTask')->assertHasErrors(['task_title'])
            ->set('task_title', 'Change dressing')->set('task_patient_id', $patient->id)->set('task_due', today()->addDay()->format('Y-m-d'))->call('addTask')->assertHasNoErrors();

        $task = $staff->tasks()->first();
        $this->assertSame($patient->id, $task->patient_id);

        $c->call('toggleTask', $task->id);
        $this->assertSame('done', $task->fresh()->status);
        $this->assertNotNull($task->fresh()->completed_at);

        $c->call('deleteTask', $task->id)->call('unassignPatient', $patient->id);
        $this->assertSame(0, $staff->tasks()->count());
        $this->assertFalse($staff->patients()->whereKey($patient->id)->exists());
    }

    public function test_attendance_is_marked_updated_and_cannot_be_in_the_future(): void
    {
        $this->loginAs('Doctor');
        $staff = $this->staff();
        $today = today()->format('Y-m-d');

        $c = Livewire::test('pages::admin.clinic.staff.show', ['staff' => $staff])
            ->set('att_date', today()->addDay()->format('Y-m-d'))->call('markAttendance')->assertHasErrors(['att_date'])
            ->set('att_date', $today)->set('att_status', 'present')->set('att_in', '09:00')->set('att_out', '08:00')->call('markAttendance')->assertHasErrors(['att_out'])
            ->set('att_out', '17:00')->call('markAttendance')->assertHasNoErrors();

        $this->assertSame(1, $staff->attendance()->count());

        $c->set('att_status', 'leave')->set('att_in', '')->set('att_out', '')->call('markAttendance'); // same day updates, no duplicate
        $this->assertSame(1, $staff->attendance()->count());
        $this->assertSame('leave', $staff->attendance()->first()->status);

        $c->call('clearAttendance', $today);
        $this->assertSame(0, $staff->attendance()->count());
    }

    public function test_shifts_cannot_overlap_and_payments_are_recorded(): void
    {
        $this->loginAs('Doctor');
        $staff = $this->staff();
        $day = today()->addDay()->format('Y-m-d');

        $c = Livewire::test('pages::admin.clinic.staff.show', ['staff' => $staff])
            ->set('shift_date', $day)->set('shift_from', '09:00')->set('shift_to', '13:00')->call('addShift')->assertHasNoErrors()
            ->set('shift_from', '12:00')->set('shift_to', '16:00')->call('addShift')->assertHasErrors(['shift_from'])
            ->set('shift_from', '14:00')->call('addShift')->assertHasNoErrors()
            ->set('shift_from', '18:00')->set('shift_to', '10:00')->call('addShift')->assertHasErrors(['shift_to']);

        $this->assertSame(2, $staff->shifts()->count());

        $c->set('pay_amount', '0')->call('addPayment')->assertHasErrors(['pay_amount'])
            ->set('pay_amount', '12000')->set('pay_type', 'salary')->call('addPayment')->assertHasNoErrors();
        $this->assertEquals(12000, $staff->payments()->sum('amount'));

        $c->call('deletePayment', $staff->payments()->first()->id)->call('deleteShift', $staff->shifts()->first()->id);
        $this->assertSame(0, $staff->payments()->count());
        $this->assertSame(1, $staff->shifts()->count());
    }

    public function test_staff_roles_are_managed_in_settings_and_protected_when_in_use(): void
    {
        $this->loginAs('Doctor');
        $this->seed(\Database\Seeders\StaffRoleSeeder::class);
        $nurse = StaffRole::where('name', 'Nurse')->first();
        Staff::create(['doctor_id' => $this->doctorId(), 'name' => 'Anita', 'staff_role_id' => $nurse->id]);

        Livewire::test('pages::admin.clinic.settings.index')
            ->set('new_staff_role', 'Nurse')->call('addStaffRole')->assertHasErrors(['new_staff_role'])
            ->set('new_staff_role', 'Lab assistant')->call('addStaffRole')->assertHasNoErrors()
            ->call('deleteStaffRole', $nurse->id)->assertHasErrors(['staff_role_delete']);

        $this->assertDatabaseHas('staff_roles', ['name' => 'Lab assistant']);
        $this->assertDatabaseHas('staff_roles', ['id' => $nurse->id]);
    }

    /* ---------- reports ---------- */

    protected function seedActivity(): void
    {
        $doctorId = $this->doctorId();
        $p1 = $this->patient('Alpha');
        $p2 = Patient::register(['doctor_id' => $doctorId, 'name' => 'Beta', 'phone' => '9000000002', 'gender' => 'female']);

        Appointment::create(['doctor_id' => $doctorId, 'patient_id' => $p1->id, 'scheduled_at' => today()->setTime(10, 0), 'status' => 'completed']);
        Appointment::create(['doctor_id' => $doctorId, 'patient_id' => $p2->id, 'scheduled_at' => today()->setTime(11, 0), 'status' => 'cancelled', 'type' => 'follow_up']);

        $c = Consultation::create(['doctor_id' => $doctorId, 'patient_id' => $p1->id, 'consulted_at' => now(), 'diagnosis' => 'Viral fever', 'treatment' => 'Rest', 'status' => 'final']);
        $c->items()->create(['medicine' => 'Paracetamol']);
        $c2 = Consultation::create(['doctor_id' => $doctorId, 'patient_id' => $p2->id, 'consulted_at' => now(), 'diagnosis' => 'Viral fever', 'status' => 'final']);
        $c2->items()->create(['medicine' => 'paracetamol']);

        $invoice = Invoice::create(['doctor_id' => $doctorId, 'patient_id' => $p1->id, 'number' => 'INV-R-1', 'invoice_date' => today()]);
        $invoice->items()->create(['category' => 'consultation', 'description' => 'Visit', 'quantity' => 1, 'unit_price' => 500, 'amount' => 500]);
        $invoice->items()->create(['category' => 'lab', 'description' => 'CBC', 'quantity' => 1, 'unit_price' => 300, 'amount' => 300]);
        $invoice->recalculate();
        $invoice->payments()->create(['patient_id' => $p1->id, 'receipt_number' => 'RCP-R-1', 'amount' => 600, 'method' => 'upi', 'paid_at' => now()]);
        $invoice->recalculate();
    }

    protected function report(string $type): array
    {
        return ClinicReports::build($type, today()->startOfMonth(), today(), $this->doctorId());
    }

    public function test_reports_calculate_the_expected_numbers(): void
    {
        $this->loginAs('Doctor');
        $this->seedActivity();

        $patients = $this->report('patients');
        $this->assertSame(2, $patients['summary']['New patients']);
        $this->assertSame(1, $patients['summary']['Female']);

        $appointments = $this->report('appointments');
        $this->assertSame(2, $appointments['summary']['Total appointments']);
        $this->assertSame(1, $appointments['summary']['Cancelled']);
        $this->assertSame(1, $appointments['summary']['Follow-ups']);

        $consultations = $this->report('consultations');
        $this->assertSame(2, $consultations['summary']['Consultations']);
        $this->assertSame('Viral fever', $consultations['summary']['Most common diagnosis']);

        $treatments = $this->report('treatments');
        $this->assertSame(2, $treatments['summary']['Prescriptions written']);
        $this->assertSame(1, $treatments['summary']['Different medicines used']); // Paracetamol / paracetamol grouped
        $this->assertSame(2, $treatments['rows'][0][1]);

        $revenue = $this->report('revenue');
        $this->assertSame('₹800.00', $revenue['summary']['Invoiced']);
        $this->assertSame('₹600.00', $revenue['summary']['Collected']);
        $this->assertSame('₹600.00', $revenue['summary']['Collected by UPI']);
        $this->assertSame('₹300.00', $revenue['summary']['Billed for Lab / Test']);

        $pending = $this->report('pending');
        $this->assertSame(1, $pending['summary']['Invoices pending']);
        $this->assertSame('₹200.00', $pending['summary']['Total pending']);
    }

    public function test_staff_report_counts_attendance_tasks_and_pay(): void
    {
        $this->loginAs('Doctor');
        $staff = $this->staff();
        $staff->attendance()->create(['work_date' => today(), 'status' => 'present']);
        $staff->attendance()->create(['work_date' => today()->subDay(), 'status' => 'absent']);
        $staff->tasks()->create(['title' => 'A', 'status' => 'done']);
        $staff->tasks()->create(['title' => 'B']);
        $staff->payments()->create(['type' => 'salary', 'amount' => 12000, 'paid_on' => today()]);

        $report = $this->report('staff');
        $this->assertSame(1, $report['summary']['Staff members']);
        $this->assertSame([ 'Nurse Anita', '', 1, 0, 0, 1, 1, 1, '₹12,000.00'], $report['rows'][0]);
    }

    public function test_reports_only_use_the_current_doctors_data(): void
    {
        $this->loginAs('Doctor');
        $this->doctorId();
        $other = Doctor::create(['name' => 'Dr. Other']);
        Patient::register(['doctor_id' => $other->id, 'name' => 'Foreign', 'phone' => '9111111111']);

        $this->assertSame(0, $this->report('patients')['summary']['New patients']);
    }

    public function test_csv_export_is_safe_and_complete(): void
    {
        $report = ['title' => 'T', 'summary' => ['Total' => '5'], 'headings' => ['Name', 'Phone'], 'rows' => [['=HYPERLINK("x")', '+911'], ['Normal', 'a,b']]];
        $out = fopen('php://memory', 'w+');
        ClinicReports::toCsv($report, $out);
        rewind($out);
        $csv = stream_get_contents($out);

        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringContainsString("'+911", $csv);
        $this->assertStringContainsString('"a,b"', $csv);
        $this->assertStringContainsString('Name,Phone', $csv);
    }

    public function test_reports_page_shows_only_permitted_tabs_and_validates_dates(): void
    {
        $this->loginAs('Receptionist');
        $this->seedActivity();

        Livewire::test('pages::admin.clinic.reports.index')
            ->assertSee('Patients')->assertSee('Appointments')->assertSee('Revenue')->assertSee('Pending payments')
            ->assertDontSee('Consultations')->assertDontSee('Treatments')
            ->call('setType', 'consultations')->assertSet('type', 'patients') // not allowed
            ->call('setType', 'revenue')->assertSee('Invoiced')
            ->set('from', today()->format('Y-m-d'))->set('to', today()->subDays(3)->format('Y-m-d'))->assertSee('The end date must be on or after the start date.')
            ->call('setRange', 'month')->assertDontSee('The end date must be on or after the start date.');
    }

    public function test_csv_download_action_works_for_allowed_reports_only(): void
    {
        $this->loginAs('Receptionist');
        $this->seedActivity();

        Livewire::test('pages::admin.clinic.reports.index')->call('setType', 'revenue')->call('exportCsv')->assertFileDownloaded();

        $nurse = $this->loginAs('Nurse'); // can see patients, not revenue
        Livewire::test('pages::admin.clinic.reports.index')->set('type', 'revenue')->call('exportCsv')->assertForbidden();
    }

    public function test_reports_need_the_view_reports_permission(): void
    {
        $this->loginAs('Receptionist'); // has no "view reports"
        $this->get(route('admin.clinic.reports'))->assertForbidden();
    }

    public function test_doctor_sees_reports_and_staff_in_the_sidebar(): void
    {
        $this->loginAs('Doctor');

        $this->get(route('admin.dashboard'))->assertOk()->assertSee('Reports')->assertSee('Staff')->assertSee('Clinic Settings');
    }

    public function test_every_clinic_page_renders_for_the_doctor(): void
    {
        $this->loginAs('Doctor');
        $this->seedActivity();
        $patient = Patient::first();
        $staff = $this->staff();
        $consultation = Consultation::first();
        $invoice = Invoice::first();

        foreach ([
            route('admin.clinic.reports'), route('admin.clinic.reports', ['type' => 'staff']), route('admin.clinic.staff.show', $staff),
            route('admin.clinic.patients.show', $patient), route('admin.clinic.patients.show', ['patient' => $patient, 'tab' => 'billing']),
            route('admin.clinic.consultations.edit', $consultation), route('admin.clinic.billing.show', $invoice), route('admin.clinic.billing.create'),
            route('admin.clinic.appointments.index'), route('admin.dashboard'),
        ] as $url) {
            $this->get($url)->assertOk();
        }
    }
}
