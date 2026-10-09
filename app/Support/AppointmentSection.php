<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Texts of the "Book your appointment" section (shown on several pages) and of the appointment popup.
 * Both are edited once in the admin (Appointment) and read everywhere through the `$appt` view variable.
 * Values live in the `settings` table; anything not saved yet falls back to these defaults.
 * The popup side text may contain {phone} and {email}, which are replaced with the Site Settings values.
 */
class AppointmentSection
{
    public static function defaults(): array
    {
        return [
            // Section
            'appt.visible' => '1',
            'appt.eyebrow' => 'Book Appointment',
            'appt.heading' => 'Book your appointment with Dr. Aditya Sharma Physician in Jaipur',
            'appt.intro' => 'Looking for the online appointment to avoid rush and easy consultation get easily your appointment with Dr. Aditya Sharma at '.\App\Support\SiteSettings::brandName().' Clinic, Jaipur.',
            'appt.clinic_name' => \App\Support\SiteSettings::brandName().' Clinic & Daycare Centre',
            'appt.form_title' => 'Make an Appointment',
            'appt.submit_text' => 'Submit',
            // Shared by the section form and the popup form
            'appt.consent' => 'By checking this box, I authorize Dr. Aditya Sharma to contact me via Call, SMS, RCS, Email, and WhatsApp.',

            // Popup
            'appt.popup_title' => 'Book An Appointment',
            'appt.popup_subtitle' => 'Contact us for any Dr. Aditya Sharma Help',
            'appt.popup_submit' => 'Send Now',
            'appt.popup_side_title' => 'Schedule an appointment',
            'appt.popup_side_text' => 'Dr. Aditya Sharma is a highly experienced physician in Jaipur, who provides the best treatment and consultation for asthma, allergy, diabetes, high BP, fever, anemia, and others. Book an appointment at {phone}.',
            'appt.popup_logo' => '', // empty = use the logo from Site Settings
        ];
    }

    /** @return array<string, mixed> */
    public static function content(): array
    {
        $v = Setting::many(self::defaults());
        $site = SiteSettings::content();

        return [
            'visible' => $v['appt.visible'] === '1',
            'eyebrow' => $v['appt.eyebrow'],
            'heading' => $v['appt.heading'],
            'intro' => $v['appt.intro'],
            'clinic_name' => $v['appt.clinic_name'],
            'form_title' => $v['appt.form_title'],
            'submit_text' => $v['appt.submit_text'],
            'consent' => $v['appt.consent'],

            'popup_title' => $v['appt.popup_title'],
            'popup_subtitle' => $v['appt.popup_subtitle'],
            'popup_submit' => $v['appt.popup_submit'],
            'popup_side_title' => $v['appt.popup_side_title'],
            'popup_side_text' => strtr($v['appt.popup_side_text'], ['{phone}' => $site['phone'], '{email}' => $site['email']]),
            'popup_side_text_raw' => $v['appt.popup_side_text'],
            'popup_logo' => $v['appt.popup_logo'],
        ];
    }
}
