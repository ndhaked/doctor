<?php

namespace Database\Seeders;

use App\Models\Specialization;
use Illuminate\Database\Seeder;

class SpecializationSeeder extends Seeder
{
    /** Common specializations; doctors can add their own in the settings. Only fills an empty table. */
    public function run(): void
    {
        if (Specialization::query()->exists()) {
            return;
        }

        $names = [
            'General Physician', 'Dentist', 'Cardiologist', 'Dermatologist', 'Pediatrician', 'Orthopedic',
            'Gynecologist', 'ENT', 'Ophthalmologist', 'Psychiatrist', 'Surgeon', 'Physiotherapist', 'Other',
        ];

        foreach ($names as $i => $name) {
            Specialization::create(['name' => $name, 'sort_order' => $i + 1]);
        }
    }
}
