<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Editable content of the "Meet our expert physician" (About Doctor) block on the home page.
 * Values live in the `settings` table; anything not saved yet falls back to these defaults.
 */
class DoctorSection
{
    public static function defaults(): array
    {
        $site = SiteSettings::content();

        return [
            'doctor.visible' => '1',
            'doctor.photo' => 'images/about-doctor.webp',
            'doctor.badge_number' => '12+',
            'doctor.badge_label' => "Years\nOf Experience",
            'doctor.eyebrow' => 'Meet Our Expert General Physician In Jaipur',
            'doctor.name' => $site['doctor_name'],
            'doctor.qualifications' => $site['doctor_qualifications'],
            'doctor.description' => "Dr. Aditya Sharma is an experienced Consultant General Physician and diabetologist with 12+ years of clinical experience treating chronic and acute conditions. He is known for a patient-first approach, combining evidence-based medicine with clear communication and follow-up care.\n\nFrom managing diabetes, hypertension, and thyroid disorders to addressing concerns like fever, infectious diseases, anemia, dengue, migraine, asthma, allergy, pain, and obesity, his dedication to patient well-being is evident.",
            'doctor.diseases_intro' => 'Dr. Aditya Sharma (Physician in Jaipur) provides the best treatment for the following diseases such as:',
            'doctor.diseases' => "Diabetes\nHypertension (high BP)\nThyroid disorders\nFever\nInfectious Diseases",
            'doctor.button_text' => 'About Us',
            'doctor.button_url' => '',
        ];
    }

    /**
     * @return array{visible: bool, photo: string, badge_number: string, badge_label: string, eyebrow: string, name: string, qualifications: string, description: string, paragraphs: array<int, string>, diseases_intro: string, diseases: string, disease_list: array<int, string>, button_text: string, button_url: string}
     */
    public static function content(): array
    {
        $v = Setting::many(self::defaults());

        $lines = fn (string $text): array => array_values(array_filter(
            array_map('trim', preg_split('/\R/', $text)),
            fn ($line) => $line !== ''
        ));

        return [
            'visible' => $v['doctor.visible'] === '1',
            'photo' => $v['doctor.photo'],
            'badge_number' => $v['doctor.badge_number'],
            'badge_label' => $v['doctor.badge_label'],
            'eyebrow' => $v['doctor.eyebrow'],
            'name' => $v['doctor.name'],
            'qualifications' => $v['doctor.qualifications'],
            'description' => $v['doctor.description'],
            'paragraphs' => array_values(array_filter(
                array_map('trim', preg_split('/\R{2,}/', $v['doctor.description'])),
                fn ($p) => $p !== ''
            )),
            'diseases_intro' => $v['doctor.diseases_intro'],
            'diseases' => $v['doctor.diseases'],
            'disease_list' => $lines($v['doctor.diseases']),
            'button_text' => $v['doctor.button_text'],
            'button_url' => $v['doctor.button_url'],
        ];
    }
}
