<?php

namespace Database\Seeders;

use App\Models\StaffRole;
use Illuminate\Database\Seeder;

class StaffRoleSeeder extends Seeder
{
    /** Common staff roles; the doctor can add or rename them in Clinic Settings. Only fills an empty table. */
    public function run(): void
    {
        if (StaffRole::query()->exists()) {
            return;
        }

        foreach (['Compounder', 'Nurse', 'Caregiver', 'Attendant', 'Receptionist', 'Technician', 'Other'] as $i => $name) {
            StaffRole::create(['name' => $name, 'sort_order' => $i + 1]);
        }
    }
}
