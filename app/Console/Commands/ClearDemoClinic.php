<?php

namespace App\Console\Commands;

use App\Models\Admin;
use App\Models\Enquiry;
use App\Models\Patient;
use App\Models\PatientDocument;
use App\Models\Staff;
use Database\Seeders\DemoClinicSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class ClearDemoClinic extends Command
{
    protected $signature = 'clinic:clear-demo {--force : Do not ask for confirmation}';

    protected $description = 'Delete the demo patients, staff, enquiries and demo logins added by DemoClinicSeeder, with everything that belongs to them';

    public function handle(): int
    {
        $patients = Patient::withTrashed()->where('notes', DemoClinicSeeder::TAG);
        $staff = Staff::where('notes', DemoClinicSeeder::TAG);
        $enquiries = Enquiry::where('user_agent', DemoClinicSeeder::TAG);

        $this->line(sprintf('Demo records found: %d patients, %d staff, %d enquiries.', (clone $patients)->count(), (clone $staff)->count(), (clone $enquiries)->count()));
        $this->line('Their appointments, consultations, prescriptions, invoices, payments, documents, attendance and tasks are removed too. Records you added yourself are kept.');

        if (! $this->option('force') && ! $this->confirm('Delete them now?')) {
            return self::SUCCESS;
        }

        foreach ((clone $patients)->get() as $patient) {
            foreach (PatientDocument::where('patient_id', $patient->id)->get() as $document) {
                Storage::disk('local')->delete($document->file);
            }

            $patient->forceDelete(); // the database removes the rest through the foreign keys
        }

        $staff->delete();
        Admin::whereIn('email', array_keys(DemoClinicSeeder::LOGINS))->delete();
        $enquiries->delete();

        $this->info('Demo data removed.');

        return self::SUCCESS;
    }
}
