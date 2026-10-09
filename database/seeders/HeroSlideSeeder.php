<?php

namespace Database\Seeders;

use App\Models\HeroSlide;
use Illuminate\Database\Seeder;

class HeroSlideSeeder extends Seeder
{
    /**
     * Seed the three slides that used to be hard-coded on the home page.
     * Only runs while the table is empty, so it never overwrites admin changes.
     */
    public function run(): void
    {
        if (HeroSlide::query()->exists()) {
            return;
        }

        $slides = [
            [
                'image' => 'images/slider-1.webp',
                'doctor_name' => 'Dr. Aditya Sharma',
                'badge' => 'Diabetes Care',
                'title' => 'Dr. Aditya Sharma',
                'credentials' => 'MBBS, MD (Internal Medicine), FAGE, CPCDM (RSSDI)',
                'specialty' => 'Consultant Physician & Diabetologist in Your City',
                'description' => 'Dr. Aditya Sharma is a highly experienced general physician & diabetologist who provides the best treatment and consultation for diabetes, hypertension, asthma, allergy, fever, anemia, and other infectious diseases.',
                'cards' => [
                    ['icon' => 'droplet', 'title' => 'Diabetes'],
                    ['icon' => 'heart', 'title' => 'Hypertension'],
                    ['icon' => 'chart', 'title' => 'Thyroid'],
                    ['icon' => 'thermometer', 'title' => 'Fever'],
                ],
                'cta_text' => 'Book an Appointment',
                'cta_action' => 'appointment',
            ],
            [
                'image' => 'images/slider-2.webp',
                'doctor_name' => 'Dr. Aditya Sharma',
                'badge' => 'Chronic Disease Management',
                'title' => 'Complete Diabetes Care, Under One Roof',
                'credentials' => 'Personalized treatment plans backed by 12+ years of clinical experience',
                'specialty' => 'Blood Sugar Monitoring & Long-Term Management',
                'description' => 'From the first diagnosis to long-term management, we help you keep blood sugar levels in check with personalized diet plans, insulin guidance, and regular follow-ups.',
                'cards' => [
                    ['icon' => 'droplet', 'title' => 'Blood Sugar Test'],
                    ['icon' => 'chart', 'title' => 'Insulin Therapy'],
                    ['icon' => 'sparkles', 'title' => 'Diet Planning'],
                    ['icon' => 'badge-check', 'title' => 'Foot Care'],
                ],
                'cta_text' => 'Explore Diabetes Care',
                'cta_action' => 'contact',
            ],
            [
                'image' => 'images/slider-3.webp',
                'doctor_name' => 'Dr. Aditya Sharma',
                'badge' => 'General Medicine',
                'title' => 'Your Trusted Family Physician',
                'credentials' => '10,000+ patients treated across all age groups',
                'specialty' => 'Preventive Care & Everyday Health, Handled With Care',
                'description' => "Whether it's a routine checkup, a sudden fever, or a health concern that needs urgent attention, our clinic is here for you and your family every step of the way.",
                'cards' => [
                    ['icon' => 'badge-check', 'title' => 'General Checkup'],
                    ['icon' => 'sparkles', 'title' => 'Vaccination'],
                    ['icon' => 'thermometer', 'title' => 'Fever Care'],
                    ['icon' => 'bolt', 'title' => 'Emergency Consult'],
                ],
                'cta_text' => 'Book a Visit',
                'cta_action' => 'appointment',
            ],
        ];

        foreach ($slides as $i => $slide) {
            HeroSlide::create($slide + ['is_active' => true, 'sort_order' => $i + 1]);
        }
    }
}
