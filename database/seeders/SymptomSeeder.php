<?php

namespace Database\Seeders;

use App\Models\Symptom;
use Illuminate\Database\Seeder;

class SymptomSeeder extends Seeder
{
    /** Seeds the symptom cards that used to be hard-coded on the home page. Only runs while the table is empty. */
    public function run(): void
    {
        if (Symptom::query()->exists()) {
            return;
        }

        $symptoms = [
            ['sick', 'Fever', 'If you have a persistent or high fever, it could be a sign of an underlying infection or illness.'],
            ['lungs', 'Respiratory Issues', 'Persistent cough, shortness of breath, or wheezing may suggest respiratory problems like bronchitis or pneumonia.'],
            ['fatigue', 'Fatigue', 'Unexplained and prolonged fatigue can be a sign of various underlying health issues, including anemia, thyroid problems, or chronic illnesses.'],
            ['pain', 'Pain', "Any unexplained or severe pain, whether it's in the abdomen, chest, joints, or muscles, should be evaluated by a general physician."],
            ['Treat-icon-8', 'Skin Problems', 'Skin conditions like rashes, hives, or persistent itching may need medical attention.'],
            ['headache', 'Headaches', 'Frequent or severe headaches, especially if they are different from your usual headaches, should be examined.'],
            ['antihistamines', 'Allergies', 'Severe allergic reactions, hives, or difficulty breathing due to allergies should prompt a visit to a doctor.'],
            ['weight-loss', 'Weight Changes', 'Unintended weight loss or gain can be indicative of various health issues and should be evaluated by a physician.'],
        ];

        foreach ($symptoms as $i => [$icon, $title, $description]) {
            Symptom::create([
                'title' => $title,
                'description' => $description,
                'icon' => 'images/symptoms/'.$icon.'.webp',
                'sort_order' => $i + 1,
            ]);
        }
    }
}
