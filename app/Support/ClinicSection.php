<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Editable content of the "About the clinic" block on the home page.
 * Texts and photo paths live in the `settings` table; anything not saved yet falls back to these defaults.
 */
class ClinicSection
{
    public const PHOTO_COUNT = 4;

    public static function defaults(): array
    {
        $defaults = [
            'clinic.eyebrow' => 'About the Best Physician Clinic in Jaipur',
            'clinic.heading' => \App\Support\SiteSettings::brandName().' Clinic & Daycare Centre, Jaipur',
            'clinic.description' => \App\Support\SiteSettings::brandName()." Clinic & Daycare Centre in Jaipur is a trusted healthcare facility that specializes in providing top-notch treatment and consultation services for a wide range of medical conditions. Led by the experienced Dr. Aditya Sharma, a skilled general physician in Jaipur & diabetologist, the clinic is dedicated to offering exceptional care to its patients, whether you're suffering from diabetes, hypertension, viral fever, asthma, allergies, joint pain, or in need of vaccinations.",
            'clinic.stat_number' => '10,000+',
            'clinic.stat_label' => 'Satisfied clients',
            'clinic.button_text' => 'Contact Us',
            'clinic.button_url' => '',
        ];

        for ($n = 1; $n <= self::PHOTO_COUNT; $n++) {
            $defaults["clinic.photo_$n"] = "images/clinic/photo-$n.webp";
        }

        return $defaults;
    }

    /**
     * @return array{eyebrow: string, heading: string, description: string, stat_number: string, stat_label: string, button_text: string, button_url: string, photos: array<int, string>}
     */
    public static function content(): array
    {
        $values = Setting::many(self::defaults());

        $photos = [];
        for ($n = 1; $n <= self::PHOTO_COUNT; $n++) {
            if ($values["clinic.photo_$n"] !== '') {
                $photos[$n] = $values["clinic.photo_$n"];
            }
        }

        return [
            'eyebrow' => $values['clinic.eyebrow'],
            'heading' => $values['clinic.heading'],
            'description' => $values['clinic.description'],
            'stat_number' => $values['clinic.stat_number'],
            'stat_label' => $values['clinic.stat_label'],
            'button_text' => $values['clinic.button_text'],
            'button_url' => $values['clinic.button_url'],
            'photos' => $photos,
        ];
    }
}
