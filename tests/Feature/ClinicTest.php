<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Appointment;
use App\Models\Consultation;
use App\Models\ConsultationField;
use App\Models\Doctor;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\PatientDocument;
use App\Models\Payment;
use App\Support\ClinicContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ClinicTest extends TestCase
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

    protected function doctor(): Doctor
    {
        return ClinicContext::doctor();
    }

    protected function patient(array $over = []): Patient
    {
        return Patient::register($over + ['doctor_id' => $this->doctor()->id, 'name' => 'Ravi Kumar', 'phone' => '9876543210']);
    }

    /* ---------- permissions ---------- */

    public function test_receptionist_sees_patients_and_billing_but_not_medical_records(): void
    {
        $this->loginAs('Receptionist');

        $this->get(route('admin.clinic.patients.index'))->assertOk();
        $this->get(route('admin.clinic.appointments.index'))->assertOk();
        $this->get(route('admin.clinic.billing.index'))->assertOk();
        $this->get(route('admin.clinic.consultations.index'))->assertForbidden();
        $this->get(route('admin.clinic.profile'))->assertForbidden();
        $this->get(route('admin.clinic.settings'))->assertForbidden();
    }

    public function test_receptionist_cannot_open_the_consultation_or_documents_of_a_patient(): void
    {
        $this->loginAs('Receptionist');
        $patient = $this->patient();
        $consultation = Consultation::create(['doctor_id' => $patient->doctor_id, 'patient_id' => $patient->id, 'consulted_at' => now()]);

        $this->get(route('admin.clinic.consultations.edit', $consultation))->assertForbidden();
        $this->get(route('admin.clinic.consultations.prescription', $consultation))->assertForbidden();

        Livewire::test('pages::admin.clinic.patients.show', ['patient' => $patient])
            ->assertDontSee('Consultations & treatment')->assertDontSee('Documents')->assertSee('Billing & payments');
    }

    public function test_doctor_role_reaches_everything_clinical(): void
    {
        $this->loginAs('Doctor');

        foreach (['admin.dashboard', 'admin.clinic.patients.index', 'admin.clinic.appointments.index', 'admin.clinic.consultations.index', 'admin.clinic.billing.index', 'admin.clinic.profile', 'admin.clinic.settings'] as $name) {
            $this->get(route($name))->assertOk();
        }
    }

    public function test_dashboard_shows_clinic_numbers_by_permission(): void
    {
        $this->loginAs('Doctor');
        $patient = $this->patient();
        Appointment::create(['doctor_id' => $patient->doctor_id, 'patient_id' => $patient->id, 'scheduled_at' => today()->setTime(10, 0)]);

        $this->get(route('admin.dashboard'))->assertOk()->assertSee('Total patients')->assertSee("Today's appointments")->assertSee('Pending payments')->assertSee('Revenue this month');
    }

    public function test_a_doctor_context_is_created_from_the_website_details(): void
    {
        $this->assertSame(0, Doctor::count());
        $doctor = ClinicContext::doctor();

        $this->assertSame('Dr. Aditya Sharma', $doctor->name);
        $this->assertSame(1, Doctor::count());
    }

    /* ---------- doctor profile ---------- */

    public function test_doctor_profile_saves_hours_fee_and_signature(): void
    {
        $this->loginAs('Doctor');

        $c = Livewire::test('pages::admin.clinic.profile.index')
            ->set('name', 'Dr. Meera Rao')->set('specialization', 'Dentist')->set('registration_no', 'MCI-1234')
            ->set('consultation_fee', '600')->set('slot_minutes', 20)
            ->set('hours.sun.open', true)->set('hours.mon.from', '10:00')->set('hours.mon.to', '14:00')
            ->set('signature_upload', UploadedFile::fake()->image('sign.png', 200, 80))
            ->call('save')->assertHasNoErrors();

        $doctor = Doctor::first();
        $this->assertSame('Dr. Meera Rao', $doctor->name);
        $this->assertSame('Dentist', $doctor->specialization);
        $this->assertEquals(600, $doctor->consultation_fee);
        $this->assertTrue($doctor->hours()['sun']['open']);
        $this->assertSame('10:00', $doctor->hours()['mon']['from']);
        $this->assertFileExists(public_path($doctor->signature));

        $c->set('remove_signature', true)->call('save');
        $this->assertNull($doctor->fresh()->signature);
        $this->assertFileDoesNotExist(public_path($doctor->signature));
    }

    public function test_working_hours_must_end_after_they_start(): void
    {
        $this->loginAs('Doctor');

        Livewire::test('pages::admin.clinic.profile.index')
            ->set('hours.mon.from', '15:00')->set('hours.mon.to', '09:00')->call('save')->assertHasErrors(['hours.mon.to']);
    }

    /* ---------- patients ---------- */

    public function test_patients_are_registered_with_a_number_and_validated(): void
    {
        $this->loginAs('Receptionist');

        Livewire::test('pages::admin.clinic.patients.index')
            ->call('create')->call('save')->assertHasErrors(['name', 'phone'])
            ->set('name', 'Sita Devi')->set('phone', 'abc')->call('save')->assertHasErrors(['phone'])
            ->set('phone', '+91 98765 43210')->set('dob', '1990-05-01')->set('gender', 'female')->set('allergies', 'Penicillin')->call('save')->assertHasNoErrors();

        $patient = Patient::first();
        $this->assertSame('Sita Devi', $patient->name);
        $this->assertSame('P-'.str_pad((string) $patient->id, 6, '0', STR_PAD_LEFT), $patient->uhid);
        $this->assertSame($this->doctor()->id, $patient->doctor_id);
    }

    public function test_patients_can_be_searched_edited_and_removed(): void
    {
        $this->loginAs('Doctor');
        $a = $this->patient(['name' => 'Alpha One', 'phone' => '9000000001']);
        $this->patient(['name' => 'Beta Two', 'phone' => '9000000002']);

        Livewire::test('pages::admin.clinic.patients.index')
            ->assertSee('Alpha One')->assertSee('Beta Two')
            ->set('search', '9000000002')->assertDontSee('Alpha One')->assertSee('Beta Two')
            ->set('search', '')
            ->call('edit', $a->id)->set('name', 'Alpha Edited')->call('save')
            ->call('delete', $a->id);

        $this->assertSoftDeleted('patients', ['id' => $a->id]);
    }

    public function test_one_doctors_patient_is_not_visible_to_another_doctor(): void
    {
        $this->loginAs('Doctor');
        $this->doctor(); // the clinic's own doctor exists first
        $other = Doctor::create(['name' => 'Dr. Other']);
        $foreign = Patient::register(['doctor_id' => $other->id, 'name' => 'Foreign Patient', 'phone' => '9111111111']);
        $this->patient();

        Livewire::test('pages::admin.clinic.patients.index')->assertDontSee('Foreign Patient');
        $this->get(route('admin.clinic.patients.show', $foreign))->assertNotFound();
    }

    public function test_patient_documents_are_private_and_served_only_to_staff(): void
    {
        Storage::fake('local');
        $this->loginAs('Doctor');
        $patient = $this->patient();

        Livewire::test('pages::admin.clinic.patients.show', ['patient' => $patient])
            ->call('setTab', 'documents')
            ->set('doc_title', 'Blood test')->set('doc_file', UploadedFile::fake()->create('report.pdf', 100, 'application/pdf'))
            ->call('addDocument')->assertHasNoErrors();

        $doc = PatientDocument::first();
        Storage::disk('local')->assertExists($doc->file);
        $this->assertStringNotContainsString('uploads', $doc->file);
        $this->get(route('admin.clinic.documents.show', $doc))->assertOk();

        Livewire::test('pages::admin.clinic.patients.show', ['patient' => $patient])->call('deleteDocument', $doc->id);
        Storage::disk('local')->assertMissing($doc->file);

        // not logged in
        auth('admin')->logout();
        $this->get(route('admin.clinic.documents.show', $doc))->assertRedirect();
    }

    public function test_document_upload_rejects_bad_files(): void
    {
        Storage::fake('local');
        $this->loginAs('Doctor');
        $patient = $this->patient();

        Livewire::test('pages::admin.clinic.patients.show', ['patient' => $patient])
            ->set('doc_title', 'Script')->set('doc_file', UploadedFile::fake()->create('x.php', 10))
            ->call('addDocument')->assertHasErrors(['doc_file']);
    }

    /* ---------- appointments ---------- */

    protected function nextWorkingDay(): string
    {
        $day = today()->addDay();

        while (! $this->doctor()->isOpenOn($day)) {
            $day->addDay();
        }

        return $day->format('Y-m-d');
    }

    public function test_appointment_booking_respects_hours_clashes_and_closed_days(): void
    {
        $this->loginAs('Receptionist');
        $patient = $this->patient();
        $day = $this->nextWorkingDay();

        $c = Livewire::test('pages::admin.clinic.appointments.index')
            ->call('openCreate')->call('choosePatient', $patient->id)
            ->set('appt_date', $day)->set('appt_time', '07:00')->call('saveAppointment')->assertHasErrors(['appt_time'])
            ->set('appt_time', '10:00')->call('saveAppointment')->assertHasNoErrors();

        $this->assertSame(1, Appointment::count());

        // same time again overlaps
        $c->call('openCreate')->call('choosePatient', $patient->id)->set('appt_date', $day)->set('appt_time', '10:05')->call('saveAppointment')->assertHasErrors(['appt_time']);

        // Sunday is closed by default
        $sunday = today()->next('Sunday')->format('Y-m-d');
        $c->set('appt_date', $sunday)->set('appt_time', '10:00')->call('saveAppointment')->assertHasErrors(['appt_date']);

        $this->assertSame(1, Appointment::count());
    }

    public function test_check_in_gives_token_numbers_and_starts_a_consultation(): void
    {
        $this->loginAs('Doctor');
        $p1 = $this->patient();
        $p2 = $this->patient(['name' => 'Second Patient', 'phone' => '9000000009']);
        $a1 = Appointment::create(['doctor_id' => $p1->doctor_id, 'patient_id' => $p1->id, 'scheduled_at' => today()->setTime(10, 0)]);
        $a2 = Appointment::create(['doctor_id' => $p1->doctor_id, 'patient_id' => $p2->id, 'scheduled_at' => today()->setTime(10, 30)]);

        $c = Livewire::test('pages::admin.clinic.appointments.index')->call('checkIn', $a2->id)->call('checkIn', $a1->id);

        $this->assertSame(1, $a2->fresh()->token_no);
        $this->assertSame(2, $a1->fresh()->token_no);
        $this->assertSame('checked_in', $a1->fresh()->status);

        $c->call('startConsultation', $a1->id);

        $this->assertSame('in_consultation', $a1->fresh()->status);
        $consultation = Consultation::where('appointment_id', $a1->id)->first();
        $this->assertNotNull($consultation);
        $c->assertRedirect(route('admin.clinic.consultations.edit', $consultation));
    }

    public function test_appointments_can_be_cancelled_or_marked_no_show(): void
    {
        $this->loginAs('Receptionist');
        $p = $this->patient();
        $a = Appointment::create(['doctor_id' => $p->doctor_id, 'patient_id' => $p->id, 'scheduled_at' => today()->setTime(11, 0)]);
        $b = Appointment::create(['doctor_id' => $p->doctor_id, 'patient_id' => $p->id, 'scheduled_at' => today()->setTime(12, 0)]);

        Livewire::test('pages::admin.clinic.appointments.index')
            ->call('askCancel', $a->id)->set('cancel_reason', 'Patient called')->call('confirmCancel')
            ->call('markNoShow', $b->id);

        $this->assertSame('cancelled', $a->fresh()->status);
        $this->assertSame('Patient called', $a->fresh()->cancel_reason);
        $this->assertSame('no_show', $b->fresh()->status);
    }

    /* ---------- consultation ---------- */

    public function test_consultation_saves_vitals_custom_fields_and_prescription_then_completes(): void
    {
        $this->loginAs('Doctor');
        $p = $this->patient();
        $appt = Appointment::create(['doctor_id' => $p->doctor_id, 'patient_id' => $p->id, 'scheduled_at' => today()->setTime(10, 0), 'status' => 'in_consultation']);
        $consultation = Consultation::create(['doctor_id' => $p->doctor_id, 'patient_id' => $p->id, 'appointment_id' => $appt->id, 'consulted_at' => now()]);
        $tooth = ConsultationField::create(['doctor_id' => $p->doctor_id, 'label' => 'Tooth number', 'type' => 'number']);
        $skin = ConsultationField::create(['doctor_id' => $p->doctor_id, 'label' => 'Skin type', 'type' => 'select', 'options' => "Dry\nOily"]);

        $c = Livewire::test('pages::admin.clinic.consultations.edit', ['consultation' => $consultation])
            ->assertSee('Tooth number')->assertSee('Skin type')
            ->set('chief_complaint', 'Tooth pain')->set('diagnosis', 'Cavity')->set('treatment', 'Filling')
            ->set('vitals.bp', '120/80')->set("custom.{$tooth->id}", '26')->set("custom.{$skin->id}", 'Oily')
            ->set('items.0.medicine', 'Amoxicillin')->set('items.0.dosage', '500 mg')->set('items.0.frequency', '1-0-1')->set('items.0.duration', '5 days')
            ->call('addItem')->set('items.1.medicine', '')
            ->set('follow_up_date', now()->addWeek()->format('Y-m-d'))
            ->call('finalize')->assertHasNoErrors();

        $consultation->refresh();
        $this->assertSame('final', $consultation->status);
        $this->assertSame('120/80', $consultation->vitals['bp']);
        $this->assertEquals('26', $consultation->custom_values[$tooth->id]);
        $this->assertSame('Oily', $consultation->custom_values[$skin->id]);
        $this->assertCount(1, $consultation->items); // blank medicine row ignored
        $this->assertSame('completed', $appt->fresh()->status);

        $c->call('reopen');
        $this->assertSame('draft', $consultation->fresh()->status);
        $this->assertSame('in_consultation', $appt->fresh()->status);
    }

    public function test_consultation_validation_and_empty_finish(): void
    {
        $this->loginAs('Doctor');
        $p = $this->patient();
        $consultation = Consultation::create(['doctor_id' => $p->doctor_id, 'patient_id' => $p->id, 'consulted_at' => now()]);
        $number = ConsultationField::create(['doctor_id' => $p->doctor_id, 'label' => 'Count', 'type' => 'number']);

        Livewire::test('pages::admin.clinic.consultations.edit', ['consultation' => $consultation])
            ->call('finalize')->assertHasErrors(['diagnosis'])
            ->set('diagnosis', 'x')->set("custom.{$number->id}", 'not a number')->call('saveDraft')->assertHasErrors(["custom.{$number->id}"])
            ->set("custom.{$number->id}", '3')->set('follow_up_date', now()->subYear()->format('Y-m-d'))->call('saveDraft')->assertHasErrors(['follow_up_date']);
    }

    public function test_nurse_can_view_but_not_change_a_consultation(): void
    {
        $this->loginAs('Nurse');
        $p = $this->patient();
        $consultation = Consultation::create(['doctor_id' => $p->doctor_id, 'patient_id' => $p->id, 'consulted_at' => now(), 'diagnosis' => 'Flu']);

        $this->get(route('admin.clinic.consultations.edit', $consultation))->assertOk();

        Livewire::test('pages::admin.clinic.consultations.edit', ['consultation' => $consultation])
            ->set('diagnosis', 'Changed')->call('saveDraft')->assertForbidden();

        $this->assertSame('Flu', $consultation->fresh()->diagnosis);
    }

    public function test_prescription_prints_with_the_doctor_details(): void
    {
        $this->loginAs('Doctor');
        $doctor = $this->doctor();
        $doctor->update(['qualification' => 'MBBS, MD', 'registration_no' => 'REG-99', 'specialization' => 'Cardiologist']);
        $p = $this->patient();
        $consultation = Consultation::create(['doctor_id' => $doctor->id, 'patient_id' => $p->id, 'consulted_at' => now(), 'diagnosis' => 'Hypertension', 'advice' => 'Less salt']);
        $consultation->items()->create(['medicine' => 'Amlodipine', 'dosage' => '5 mg', 'frequency' => '0-0-1', 'duration' => '30 days']);

        $this->get(route('admin.clinic.consultations.prescription', $consultation))
            ->assertOk()->assertSee('Ravi Kumar')->assertSee('Hypertension')->assertSee('Amlodipine')->assertSee('REG-99')->assertSee('Cardiologist')->assertSee('Less salt');
    }

    /* ---------- billing ---------- */

    public function test_invoice_is_created_paid_in_parts_and_receipted(): void
    {
        $this->loginAs('Receptionist');
        $p = $this->patient();
        $this->doctor()->update(['consultation_fee' => 500]);

        $c = Livewire::test('pages::admin.clinic.billing.show')
            ->call('choosePatient', $p->id)
            ->assertSet('items.0.unit_price', '500')
            ->call('addItem')->set('items.1.description', 'Dressing')->set('items.1.unit_price', '200')->set('items.1.quantity', '2')
            ->set('discount', '100')
            ->call('save')->assertHasNoErrors();

        $invoice = Invoice::first();
        $this->assertSame('INV-'.now()->format('Y').'-'.str_pad((string) $invoice->id, 5, '0', STR_PAD_LEFT), $invoice->number);
        $this->assertEquals(900, $invoice->subtotal);
        $this->assertEquals(800, $invoice->total);
        $this->assertSame('unpaid', $invoice->status);

        $page = Livewire::test('pages::admin.clinic.billing.show', ['invoice' => $invoice])
            ->set('pay_amount', '300')->set('pay_method', 'upi')->call('addPayment')->assertHasNoErrors();
        $this->assertSame('partial', $invoice->fresh()->status);
        $this->assertEquals(500, $invoice->fresh()->due());

        $page->set('pay_amount', '600')->call('addPayment')->assertHasErrors(['pay_amount']); // more than due
        $page->set('pay_amount', '500')->set('pay_method', 'cash')->call('addPayment')->assertHasNoErrors();
        $this->assertSame('paid', $invoice->fresh()->status);

        $payment = Payment::first();
        $this->assertSame('RCP-'.str_pad((string) $payment->id, 6, '0', STR_PAD_LEFT), $payment->receipt_number);

        $this->get(route('admin.clinic.billing.print', $invoice))->assertOk()->assertSee($invoice->number)->assertSee('Dressing');
        $this->get(route('admin.clinic.payments.receipt', $payment))->assertOk()->assertSee($payment->receipt_number);

        $page->call('deletePayment', $payment->id);
        $this->assertSame('partial', $invoice->fresh()->status);
    }

    public function test_invoice_total_cannot_drop_below_what_is_paid_and_paid_invoices_cannot_be_cancelled(): void
    {
        $this->loginAs('Receptionist');
        $p = $this->patient();
        $invoice = Invoice::create(['doctor_id' => $p->doctor_id, 'patient_id' => $p->id, 'number' => 'INV-T-1', 'invoice_date' => today()]);
        $invoice->items()->create(['category' => 'consultation', 'description' => 'Visit', 'quantity' => 1, 'unit_price' => 400, 'amount' => 400]);
        $invoice->recalculate();
        $invoice->payments()->create(['patient_id' => $p->id, 'receipt_number' => 'RCP-T-1', 'amount' => 300, 'method' => 'cash', 'paid_at' => now()]);
        $invoice->recalculate();

        Livewire::test('pages::admin.clinic.billing.show', ['invoice' => $invoice])
            ->set('items.0.unit_price', '100')->call('save')->assertHasErrors(['discount'])
            ->call('cancelInvoice')->assertHasErrors(['cancel']);

        $this->assertEquals(400, $invoice->fresh()->total);
        $this->assertNotSame('cancelled', $invoice->fresh()->status);
    }

    public function test_unpaid_invoice_can_be_cancelled_and_is_then_locked(): void
    {
        $this->loginAs('Receptionist');
        $p = $this->patient();
        $invoice = Invoice::create(['doctor_id' => $p->doctor_id, 'patient_id' => $p->id, 'number' => 'INV-T-2', 'invoice_date' => today()]);
        $invoice->items()->create(['category' => 'other', 'description' => 'X', 'quantity' => 1, 'unit_price' => 50, 'amount' => 50]);
        $invoice->recalculate();

        Livewire::test('pages::admin.clinic.billing.show', ['invoice' => $invoice])->call('cancelInvoice');
        $this->assertSame('cancelled', $invoice->fresh()->status);
        $this->assertEquals(0, $invoice->fresh()->due());

        Livewire::test('pages::admin.clinic.billing.show', ['invoice' => $invoice->fresh()])->set('pay_amount', '10')->call('addPayment')->assertForbidden();
    }

    public function test_invoice_requires_patient_and_valid_items(): void
    {
        $this->loginAs('Receptionist');

        Livewire::test('pages::admin.clinic.billing.show')
            ->set('items.0.description', '')->call('save')->assertHasErrors(['patient_id', 'items.0.description']);

        $this->assertSame(0, Invoice::count());
    }

    public function test_billing_list_shows_totals_and_filters(): void
    {
        $this->loginAs('Receptionist');
        $p = $this->patient();
        $paid = Invoice::create(['doctor_id' => $p->doctor_id, 'patient_id' => $p->id, 'number' => 'INV-PAID', 'invoice_date' => today(), 'total' => 100, 'paid_amount' => 100, 'status' => 'paid']);
        Invoice::create(['doctor_id' => $p->doctor_id, 'patient_id' => $p->id, 'number' => 'INV-OWES', 'invoice_date' => today(), 'total' => 250, 'paid_amount' => 50, 'status' => 'partial']);

        Livewire::test('pages::admin.clinic.billing.index')
            ->assertSee('INV-PAID')->assertSee('INV-OWES')
            ->set('filter', 'owing')->assertDontSee('INV-PAID')->assertSee('INV-OWES')
            ->assertSee('200.00'); // pending = 250 - 50
    }

    /* ---------- settings ---------- */

    public function test_settings_manage_specializations_fields_and_billing_texts(): void
    {
        $this->loginAs('Doctor');
        $this->seed(\Database\Seeders\SpecializationSeeder::class);

        $c = Livewire::test('pages::admin.clinic.settings.index')
            ->set('new_specialization', 'Dentist')->call('addSpecialization')->assertHasErrors(['new_specialization']) // already in the list
            ->set('new_specialization', 'Rheumatologist')->call('addSpecialization')->assertHasNoErrors()
            ->call('createField')->set('field_label', 'Skin type')->set('field_type', 'select')->set('field_options', 'Dry')->call('saveField')->assertHasErrors(['field_options'])
            ->set('field_options', "Dry\nOily")->call('saveField')->assertHasNoErrors()
            ->set('currency', '$')->set('invoice_prefix', 'BILL')->set('visit_fee_label', 'Visit')->call('saveBilling')->assertHasNoErrors();

        $this->assertDatabaseHas('specializations', ['name' => 'Rheumatologist']);
        $this->assertSame(1, ConsultationField::count());
        $this->assertSame('$', \App\Support\ClinicSettings::content()['currency']);
        $this->assertSame('BILL', \App\Support\ClinicSettings::content()['invoice_prefix']);

        $field = ConsultationField::first();
        $c->call('toggleField', $field->id);
        $this->assertFalse($field->fresh()->is_active);
        $c->call('deleteField', $field->id);
        $this->assertSame(0, ConsultationField::count());
    }

    public function test_invoice_prefix_must_be_letters_and_numbers(): void
    {
        $this->loginAs('Doctor');

        Livewire::test('pages::admin.clinic.settings.index')->set('invoice_prefix', 'IN V!')->call('saveBilling')->assertHasErrors(['invoice_prefix']);
    }

    public function test_sidebar_groups_follow_permissions(): void
    {
        $this->loginAs('Receptionist');

        $this->get(route('admin.dashboard'))->assertOk()
            ->assertSee('Patients')->assertSee('Appointments')->assertSee('Billing &amp; Payments', false)
            ->assertDontSee('Doctor Profile')->assertDontSee('Consultations');
    }
}
