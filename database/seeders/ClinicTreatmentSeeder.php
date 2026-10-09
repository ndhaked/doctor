<?php

namespace Database\Seeders;

use App\Models\ClinicTreatment;
use Illuminate\Database\Seeder;

class ClinicTreatmentSeeder extends Seeder
{
    /** Seeds the icon cards that used to be hard-coded in the "About the clinic" block. Only runs while the table is empty. */
    public function run(): void
    {
        if (ClinicTreatment::query()->exists()) {
            return;
        }

        $cards = [
            ['Diabetes', 'clinic/cl-3.png'],
            ['Hypertension (high BP)', 'clinic/cl-1.png'],
            ['Thyroid disorders', 'clinic/thyroid.png'],
            ['Fever', 'symptoms/sick.webp'],
            ['Infectious Diseases', 'clinic/cl-7.png'],
            ['Anemia', 'clinic/cl-2.png'],
            ['Dengue', 'clinic/cl-8.png'],
            ['Migraine', 'clinic/cl-5.png'],
            ['Asthma', 'symptoms/lungs.webp'],
            ['Allergy', 'clinic/cl-4.png'],
        ];

        foreach ($cards as $i => [$title, $image]) {
            ClinicTreatment::create(['title' => $title, 'image' => 'images/'.$image, 'sort_order' => $i + 1]);
        }
    }
}
