<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Editable content of the dark green "Associate With" band on the home page
 * (doctor on the left, "associate with" badge in the middle, hospital on the right).
 * Values live in the `settings` table; anything not saved yet falls back to these defaults.
 */
class AssociateSection
{
    public static function defaults(): array
    {
        $site = SiteSettings::content();

        return [
            'assoc.visible' => '1',

            // Doctor
            'assoc.photo' => 'images/director.webp',
            'assoc.name' => $site['doctor_name'],
            'assoc.qualifications' => $site['doctor_qualifications'],
            'assoc.tagline' => 'General Physician in Jaipur',
            'assoc.button_text' => 'View Profile',
            'assoc.button_url' => '',

            // Middle badge
            'assoc.badge_image' => 'images/associate.png',
            'assoc.badge_text' => 'Associate With',

            // Hospital
            'assoc.hospital_image' => 'images/hospital.webp',
            'assoc.hospital_link' => '',
            'assoc.hospital_name' => 'City Multi-Specialty Hospital',
            'assoc.hospital_role' => 'Consultant Physician & Diabetologist',
            'assoc.hospital_text' => 'Dr. Aditya Sharma is one of the best physicians and diabetologists in Jaipur, currently working at City Multi-Specialty Hospital. He has 12+ years of experience in this field.',
        ];
    }

    /** @return array<string, mixed> */
    public static function content(): array
    {
        $v = Setting::many(self::defaults());

        return [
            'visible' => $v['assoc.visible'] === '1',
            'photo' => $v['assoc.photo'],
            'name' => $v['assoc.name'],
            'qualifications' => $v['assoc.qualifications'],
            'tagline' => $v['assoc.tagline'],
            'button_text' => $v['assoc.button_text'],
            'button_url' => $v['assoc.button_url'] !== '' ? $v['assoc.button_url'] : route('about'),
            'badge_image' => $v['assoc.badge_image'],
            'badge_text' => $v['assoc.badge_text'],
            'hospital_image' => $v['assoc.hospital_image'],
            'hospital_link' => $v['assoc.hospital_link'] !== '' ? $v['assoc.hospital_link'] : route('contact'),
            'hospital_name' => $v['assoc.hospital_name'],
            'hospital_role' => $v['assoc.hospital_role'],
            'hospital_text' => $v['assoc.hospital_text'],
        ];
    }
}
