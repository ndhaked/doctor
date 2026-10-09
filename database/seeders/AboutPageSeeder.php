<?php

namespace Database\Seeders;

use App\Models\AboutExpertise;
use App\Models\AboutTreatment;
use Illuminate\Database\Seeder;

class AboutPageSeeder extends Seeder
{
    /** Seeds the cards that used to be hard-coded on the About Doctor page. Each list only fills while it is empty. */
    public function run(): void
    {
        if (! AboutExpertise::query()->exists()) {
            $expertise = [
                ['Diabetes', 'cl-3.png'], ['Hypertension (high BP)', 'cl-1.png'], ['Thyroid disorder', 'thyroid.png'],
                ['Fever', 'sick-1.png'], ['Infectious Diseases', 'infected.png'], ['Anemia', 'cl-2.png'],
                ['Dengue', 'cl-8.png'], ['Migraine', 'cl-5.png'], ['Asthma', 'asthma-2.png'],
                ['Allergy', 'antihistamines-1.png'], ['Pain', 'pain-1.png'], ['Obesity', 'fat.png'],
            ];

            foreach ($expertise as $i => [$title, $file]) {
                AboutExpertise::create(['title' => $title, 'image' => 'images/about/'.$file, 'sort_order' => $i + 1]);
            }
        }

        if (! AboutTreatment::query()->exists()) {
            $treatments = [
                ['Diabetes', 'services-5.jpg'], ['Hypertension (high BP)', 'tr-1.png'], ['Thyroid disorders', 'thyroid-service.jpg'],
                ['Fever', 'viral-fever.jpg'], ['Infectious Diseases', 'Infectious-Diseases.png'], ['Anemia', 'anemia.jpg'],
                ['Dengue', 'dengue.png'], ['Migraine', 'st-5.png'], ['Asthma', 'services-7.png'], ['Allergy', 'Allergy.png'],
            ];

            foreach ($treatments as $i => [$title, $file]) {
                AboutTreatment::create(['title' => $title, 'image' => 'images/about/'.$file, 'sort_order' => $i + 1]);
            }
        }
    }
}
