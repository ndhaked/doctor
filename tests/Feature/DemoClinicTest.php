<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Appointment;
use App\Models\Enquiry;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\PatientDocument;
use App\Models\Staff;
use App\Support\ClinicContext;
use App\Support\ClinicReports;
use Database\Seeders\DemoClinicSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DemoClinicTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_data_fills_every_area_and_every_page_opens_with_it(): void
    {
        Storage::fake('local');
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $this->seed(DemoClinicSeeder::class);

        $this->assertSame(24, Patient::count());
        $this->assertGreaterThan(30, Appointment::count());
        $this->assertGreaterThan(0, Invoice::owing()->count());
        $this->assertSame(5, Staff::count());
        $this->assertSame(8, PatientDocument::count());
        $this->assertSame(5, Enquiry::count());

        $admin = Admin::factory()->create();
        $admin->assignRole('Super Admin');
        $this->actingAs($admin, 'admin');

        $patient = Patient::first();
        $document = PatientDocument::first();

        foreach ([
            route('admin.dashboard'), route('admin.clinic.patients.index'), route('admin.clinic.patients.show', $patient), route('admin.clinic.appointments.index'),
            route('admin.clinic.consultations.index'), route('admin.clinic.billing.index'), route('admin.clinic.staff.index'), route('admin.clinic.staff.show', Staff::first()),
            route('admin.clinic.documents.show', $document), route('admin.enquiries.index'),
        ] as $url) {
            $this->get($url)->assertOk();
        }

        // every report has something to show
        foreach (array_keys(ClinicReports::TYPES) as $type) {
            $report = ClinicReports::build($type, today()->subDays(120), today(), ClinicContext::id());
            $this->assertNotEmpty($report['rows'], "$type report is empty");
        }
    }

    public function test_seeding_twice_does_not_duplicate_and_demo_data_can_be_cleared_without_touching_real_records(): void
    {
        Storage::fake('local');
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $this->seed(DemoClinicSeeder::class);
        $this->seed(DemoClinicSeeder::class);
        $this->assertSame(24, Patient::count());

        $real = Patient::register(['doctor_id' => ClinicContext::id(), 'name' => 'Real Patient', 'phone' => '9000000000']);
        $realStaff = Staff::create(['doctor_id' => ClinicContext::id(), 'name' => 'Real Staff']);

        $this->artisan('clinic:clear-demo', ['--force' => true])->assertSuccessful();

        $this->assertSame(1, Patient::count());
        $this->assertTrue(Patient::whereKey($real->id)->exists());
        $this->assertSame(0, Appointment::count());
        $this->assertSame(0, Invoice::count());
        $this->assertSame(0, PatientDocument::count());
        $this->assertSame(0, Enquiry::count());
        $this->assertSame([$realStaff->id], Staff::pluck('id')->all());
    }

    public function test_seeder_skips_when_the_clinic_already_has_patients(): void
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        Patient::register(['doctor_id' => ClinicContext::id(), 'name' => 'Existing', 'phone' => '9000000000']);

        $this->seed(DemoClinicSeeder::class);

        $this->assertSame(1, Patient::count());
    }
}
