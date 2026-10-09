<?php

namespace Database\Seeders;

use App\Models\WhyChooseItem;
use Illuminate\Database\Seeder;

class WhyChooseItemSeeder extends Seeder
{
    /** Seeds the accordion rows that used to be hard-coded on the home page. Only runs while the table is empty. */
    public function run(): void
    {
        if (WhyChooseItem::query()->exists()) {
            return;
        }

        $items = [
            ['Expertise', 'Dr. Aditya Sharma has experience in diagnosing and managing a wide range of medical conditions. His expertise includes preventive healthcare, diabetes management, lifestyle-related disorders, and general medical care with a focus on accurate diagnosis and effective treatment.'],
            ['Reputation', 'Thousands of patients across Jaipur trust Dr. Sharma for his honest advice, clear communication and consistent, compassionate approach.'],
            ['Commitment to excellence', 'Dr. Aditya Sharma is committed to staying updated with the latest advancements in medicine and providing high-quality healthcare with a focus on patient well-being and effective treatment.'],
        ];

        foreach ($items as $i => [$title, $content]) {
            WhyChooseItem::create(['title' => $title, 'content' => $content, 'sort_order' => $i + 1]);
        }
    }
}
