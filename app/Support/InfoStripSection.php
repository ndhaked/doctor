<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Editable texts of the four-column info strip right under the home page banner
 * (doctor info, Google reviews, contact, quick booking form).
 *
 * The rating, review count and button link of the Google column come from the Reviews section, and the
 * phone / email of the contact column from Site Settings, so they stay the same everywhere.
 * Values live in the `settings` table; anything not saved yet falls back to these defaults.
 */
class InfoStripSection
{
    public static function defaults(): array
    {
        $site = SiteSettings::content();

        return [
            'strip.visible' => '1',

            // Column 1 - doctor info
            'strip.doctor_name' => $site['doctor_name'],
            'strip.doctor_text' => $site['doctor_name'].' is a Highly Experienced Physician & Diabetologist in Your City.',
            'strip.doctor_button_text' => 'Doctor Info',
            'strip.doctor_button_url' => '#about',

            // Column 2 - Google reviews
            'strip.reviews_title' => 'Google Reviews',

            // Column 3 - contact
            'strip.contact_title' => 'Contact Us Anytime',
            'strip.contact_clinic_label' => 'Clinic',
            'strip.contact_link_text' => 'Get Direction',
            'strip.contact_link_url' => '',

            // Column 4 - quick booking form
            'strip.book_title' => 'Book Appointment',
            'strip.book_button_text' => 'Book Appointment',
        ];
    }

    /** @return array<string, mixed> */
    public static function content(): array
    {
        $v = Setting::many(self::defaults());

        return [
            'visible' => $v['strip.visible'] === '1',
            'doctor_name' => $v['strip.doctor_name'],
            'doctor_text' => $v['strip.doctor_text'],
            'doctor_button_text' => $v['strip.doctor_button_text'],
            'doctor_button_url' => $v['strip.doctor_button_url'] !== '' ? $v['strip.doctor_button_url'] : '#about',
            'reviews_title' => $v['strip.reviews_title'],
            'contact_title' => $v['strip.contact_title'],
            'contact_clinic_label' => $v['strip.contact_clinic_label'],
            'contact_link_text' => $v['strip.contact_link_text'],
            'contact_link_url' => $v['strip.contact_link_url'] !== '' ? $v['strip.contact_link_url'] : route('contact'),
            'book_title' => $v['strip.book_title'],
            'book_button_text' => $v['strip.book_button_text'],
        ];
    }
}
