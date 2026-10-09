<?php

namespace Database\Seeders;

use App\Models\Service;
use Illuminate\Database\Seeder;

class ServiceSeeder extends Seeder
{
    /** Seeds the service cards that used to be hard-coded on the home page. Only runs while the table is empty. */
    public function run(): void
    {
        if (Service::query()->exists()) {
            return;
        }

        $services = [
            ['Diabetes', 'droplet', null, 'It is a chronic medical condition characterized by high levels of glucose in the blood. It can result from inadequate insulin production or the body\'s inability to effectively use insulin.'],
            ['Hypertension (high BP)', 'heart', 'images/services/hypertension.webp', 'It is a medical condition characterized by elevated force of blood against the walls of the arteries. It can increase the risk of heart disease, stroke, and other health problems.'],
            ['Thyroid Disorders', 'chart', null, 'It refers to conditions that affect the thyroid gland\'s function, leading to an imbalance in the production of thyroid hormones, which regulate metabolism and various bodily functions.'],
            ['Fever', 'thermometer', null, 'It is a common medical condition characterized by a sudden onset of fever and other flu-like symptoms, often caused by various viral infections such as the flu or common cold.'],
            ['Anemia', 'droplet', null, 'It is a medical condition characterized by a deficiency of red blood cells in the blood, leading to reduced oxygen-carrying capacity and often resulting in fatigue and weakness.'],
            ['Dengue', 'bug', null, 'It is a mosquito-borne viral infection that causes flu-like symptoms, including high fever, severe headache, pain behind the eyes, joint and muscle pain, and a skin rash.'],
            ['Asthma', 'lungs', 'images/services/asthma.webp', 'It is a chronic respiratory condition characterized by inflammation and narrowing of the airways, leading to symptoms like wheezing, coughing, and shortness of breath.'],
            ['Migraine/Headache', 'bolt', null, 'It is a painful sensation or discomfort in the head, often characterized by throbbing, pressure, or aching. It can be caused by various factors, including stress and dehydration.'],
        ];

        foreach ($services as $i => [$title, $icon, $image, $description]) {
            Service::create([
                'title' => $title,
                'icon' => $icon,
                'image' => $image,
                'description' => $description,
                'sort_order' => $i + 1,
            ]);
        }
    }
}
