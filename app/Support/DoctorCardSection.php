<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Editable content of the "doctor card" block on the home page
 * (round photo, name, address, buttons, treatment icons and the clinic poster).
 * Values live in the `settings` table; anything not saved yet falls back to these defaults.
 */
class DoctorCardSection
{
    public const ACTIONS = [
        'appointment' => 'Open appointment popup',
        'contact' => 'Go to Contact Us page',
        'custom' => 'Custom link',
    ];

    public static function defaults(): array
    {
        $site = SiteSettings::content();

        return [
            'card.visible' => '1',
            'card.photo' => 'images/director.webp',
            'card.name' => $site['doctor_name'],
            'card.qualifications' => $site['doctor_qualifications'],
            // Empty address = the clinic name plus the address from Site Settings; any text here replaces it.
            'card.address' => '',
            'card.show_address' => '1',
            'card.show_phone' => '1',
            'card.experience' => '12+ Years of Experience',
            'card.button_text' => 'Book Appointment',
            'card.button_action' => 'appointment',
            'card.button_url' => '',
            'card.poster' => 'images/clinic-poster.webp',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function content(): array
    {
        $v = Setting::many(self::defaults());
        $site = SiteSettings::content();

        $address = $v['card.show_address'] === '1'
            ? ($v['card.address'] !== '' ? $v['card.address'] : trim(\App\Support\SiteSettings::brandName().' Clinic, '.$site['address'], ' ,'))
            : '';
        $phone = $v['card.show_phone'] === '1' ? $site['phone'] : '';

        return [
            'visible' => $v['card.visible'] === '1',
            'photo' => $v['card.photo'],
            'name' => $v['card.name'],
            'qualifications' => $v['card.qualifications'],
            'address' => $address,
            'address_custom' => $v['card.address'],
            'show_address' => $v['card.show_address'] === '1',
            'experience' => $v['card.experience'],
            'button_text' => $v['card.button_text'],
            'button_action' => $v['card.button_action'],
            'button_url' => $v['card.button_url'],
            'phone' => $phone,
            'phone_href' => $phone !== '' ? $site['phone_href'] : '',
            'show_phone' => $v['card.show_phone'] === '1',
            'poster' => $v['card.poster'],
        ];
    }

    /** Where the first button should point, based on its chosen action. */
    public static function buttonHref(array $content): string
    {
        return match ($content['button_action']) {
            'contact' => route('contact'),
            'custom' => $content['button_url'] !== '' ? $content['button_url'] : '#',
            default => '#appointment',
        };
    }
}
