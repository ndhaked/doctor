<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Details shared by many pages: contact info, opening hours, social links, footer texts and the map.
 * They are edited once in the admin (Site Settings) and read everywhere through the `$site` view variable.
 * Values live in the `settings` table; anything not saved yet falls back to these defaults.
 */
class SiteSettings
{
    public static function defaults(): array
    {
        return [
            // The doctor's identity is the default for every block that shows it (each block can override it)
            'site.doctor_name' => 'Dr. Aditya Sharma',
            'site.doctor_qualifications' => "MBBS, MD (Internal Medicine), FAGE, CPCDM (RSSDI)\nConsultant Physician & Diabetologist",

            // Logo image (path relative to /public; empty = the built-in heart icon and clinic name) and the bar colours
            'site.logo' => '',
            'site.topbar_bg' => '#022c22',
            'site.topbar_text' => '#ecfdf5',
            'site.header_bg' => '#ffffff',

            // Brand shown in the header, footer, popup and browser titles (so one codebase can serve any clinic or doctor)
            'site.brand_name' => '',
            'site.brand_tagline' => 'Clinic',
            'site.meta_title' => 'Consultant Physician & Diabetologist',

            'site.phone' => '+91 99999 99999',
            'site.email' => 'care@doctorweb.test',
            'site.address' => '123 MG Road, Near City Hospital, Your City',

            'site.timing_title' => 'Dr. Aditya Sharma Consultation',
            'site.timing_days' => 'Mon To Sat',
            'site.timing_hours' => '8:30 AM to 7:30 PM',
            'site.timing_note' => 'Consultation with Prior Appointment Only',

            'site.facebook' => '#',
            'site.instagram' => '#',
            'site.youtube' => '#',

            'site.footer_text' => 'Dr. Aditya Sharma is a highly experienced physician in Jaipur, who provides the best treatment and consultation for asthma, allergy, diabetes, high BP, fever, anemia, and others. Book an appointment at {phone}.',
            'site.footer_links' => "Physician in Jaipur|#\nDiabetologist in Jaipur|#\nAsthma Specialist in Jaipur|#\nDiabetes Clinic in Jaipur|#\nHypertension Treatment in Jaipur|#\nThyroid Treatment in Jaipur|#\nFever Treatment in Jaipur|#",
            'site.copyright_name' => 'Dr. Aditya Sharma',
            'site.credit_name' => 'Nirbhay Dhaked',

            'site.map_url' => 'https://www.google.com/maps?q=Jaipur,+Rajasthan&output=embed',

            // Where a copy of every new website enquiry is emailed (empty = no email, enquiries still reach the admin inbox)
            'site.notify_email' => '',
        ];
    }

    /** The clinic / brand name set in Site Settings, falling back to the application name. */
    public static function brandName(): string
    {
        $name = trim((string) Setting::get('site.brand_name', ''));

        return $name !== '' ? $name : config('app.name');
    }

    /** Only a plain #rrggbb value is ever printed into a style attribute. */
    public static function color(?string $value, string $fallback): string
    {
        return preg_match('/^#[0-9a-fA-F]{6}$/', (string) $value) ? $value : $fallback;
    }

    /**
     * @return array<string, mixed>
     */
    public static function content(): array
    {
        $v = Setting::many(self::defaults());

        $footerLinks = [];
        foreach (preg_split('/\R/', $v['site.footer_links']) as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            [$label, $url] = array_pad(array_map('trim', explode('|', $line, 2)), 2, '#');
            $footerLinks[] = ['label' => $label, 'url' => $url !== '' ? $url : '#'];
        }

        return [
            'brand_name' => $brandName = trim($v['site.brand_name']) !== '' ? trim($v['site.brand_name']) : config('app.name'),
            'brand_name_raw' => $v['site.brand_name'],
            'brand_tagline' => trim($v['site.brand_tagline']),
            'brand_full' => trim($brandName.' '.trim($v['site.brand_tagline'])),
            'meta_title' => $v['site.meta_title'],
            'logo' => $v['site.logo'],
            'logo_url' => $v['site.logo'] !== '' ? asset($v['site.logo']) : null,
            'topbar_bg' => self::color($v['site.topbar_bg'], '#022c22'),
            'topbar_text' => self::color($v['site.topbar_text'], '#ecfdf5'),
            'header_bg' => self::color($v['site.header_bg'], '#ffffff'),
            'doctor_name' => $v['site.doctor_name'],
            'doctor_qualifications' => $v['site.doctor_qualifications'],
            'phone' => $v['site.phone'],
            'phone_href' => $v['site.phone'] !== '' ? 'tel:'.preg_replace('/[^\d+]/', '', $v['site.phone']) : '',
            'email' => $v['site.email'],
            'address' => $v['site.address'],

            'timing_title' => $v['site.timing_title'],
            'timing_days' => $v['site.timing_days'],
            'timing_hours' => $v['site.timing_hours'],
            'timing_note' => $v['site.timing_note'],

            'facebook' => $v['site.facebook'],
            'instagram' => $v['site.instagram'],
            'youtube' => $v['site.youtube'],

            'footer_text' => strtr($v['site.footer_text'], ['{phone}' => $v['site.phone'], '{email}' => $v['site.email']]),
            'footer_text_raw' => $v['site.footer_text'],
            'footer_links_text' => $v['site.footer_links'],
            'footer_links' => $footerLinks,
            'copyright_name' => $v['site.copyright_name'],
            'credit_name' => $v['site.credit_name'],

            'map_url' => $v['site.map_url'],

            'notify_email' => $v['site.notify_email'],
        ];
    }
}
