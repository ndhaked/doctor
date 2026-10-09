<?php

namespace Database\Seeders;

use App\Models\ProfileTreatment;
use Illuminate\Database\Seeder;

class ProfileTreatmentSeeder extends Seeder
{
    /** Seeds the icon grid that used to be hard-coded in the doctor card block. Only runs while the table is empty. */
    public function run(): void
    {
        if (ProfileTreatment::query()->exists()) {
            return;
        }

        $items = [
            ['Diabetes', 'images/symptoms/Diabetes-icon.webp', null],
            ['Hypertension (high BP)', 'images/symptoms/Treat-icon-1.webp', null],
            ['Thyroid disorders', 'images/symptoms/thyroid-1.webp', null],
            ['Fever', 'images/symptoms/sick.webp', null],
            ['Infectious Diseases', null, 'bug'],
            ['Anemia', 'images/symptoms/anemia.webp', null],
            ['Dengue', null, 'droplet'],
            ['Migraine', 'images/symptoms/headache.webp', null],
        ];

        foreach ($items as $i => [$title, $image, $icon]) {
            ProfileTreatment::create(['title' => $title, 'image' => $image, 'icon' => $icon, 'sort_order' => $i + 1]);
        }
    }
}
