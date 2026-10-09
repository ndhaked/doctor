<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Editable texts of the Contact Us page. The phone, email, address, opening hours and map come from
 * Site Settings, the consent text from Appointment, and the reviews from Reviews, so they stay the same everywhere.
 * Values live in the `settings` table; anything not saved yet falls back to these defaults.
 */
class ContactPageSection
{
    public static function defaults(): array
    {
        $site = SiteSettings::content();

        return [
            // Banner
            'contactpage.title' => 'Contact Us',
            'contactpage.intro' => 'Looking for the best physician in Jaipur? Book an appointment with '.$site['doctor_name'].' with the contact details below and confirm your appointment.',

            // "Get In Touch" cards
            'contactpage.info_eyebrow' => 'Contact Us',
            'contactpage.info_heading' => 'Get In Touch!',
            'contactpage.info_text' => 'Book an appointment with '.$site['doctor_name'].' at '.\App\Support\SiteSettings::brandName().' Clinic & Daycare Centre with the below details.',
            'contactpage.phone_title' => 'Phone',
            'contactpage.hours_title' => 'Opening Hours',
            'contactpage.address_title' => 'Clinic Address',

            // Query form
            'contactpage.form_visible' => '1',
            'contactpage.photo' => 'images/contact/doctor.jpg',
            'contactpage.name' => $site['doctor_name'],
            'contactpage.qualifications' => $site['doctor_qualifications'],
            'contactpage.form_heading' => 'Have You Any Queries?',
            'contactpage.form_text' => 'Just simple fill out this form and get contact with us for your queries and your health condition treatments.',
            'contactpage.form_button' => 'Send Now',

            // Map
            'contactpage.map_visible' => '1',
        ];
    }

    /** @return array<string, mixed> */
    public static function content(): array
    {
        $v = Setting::many(self::defaults());

        return [
            'title' => $v['contactpage.title'],
            'intro' => $v['contactpage.intro'],
            'info_eyebrow' => $v['contactpage.info_eyebrow'],
            'info_heading' => $v['contactpage.info_heading'],
            'info_text' => $v['contactpage.info_text'],
            'phone_title' => $v['contactpage.phone_title'],
            'hours_title' => $v['contactpage.hours_title'],
            'address_title' => $v['contactpage.address_title'],
            'form_visible' => $v['contactpage.form_visible'] === '1',
            'photo' => $v['contactpage.photo'],
            'name' => $v['contactpage.name'],
            'qualifications' => $v['contactpage.qualifications'],
            'form_heading' => $v['contactpage.form_heading'],
            'form_text' => $v['contactpage.form_text'],
            'form_button' => $v['contactpage.form_button'],
            'map_visible' => $v['contactpage.map_visible'] === '1',
        ];
    }
}
