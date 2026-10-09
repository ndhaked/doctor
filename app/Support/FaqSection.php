<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Editable texts and settings for the FAQ block on the home page.
 * Values live in the `settings` table; anything not saved yet falls back to these defaults.
 */
class FaqSection
{
    public static function defaults(): array
    {
        return [
            // Home page block
            'faq.home_visible' => '1',
            'faq.home_eyebrow' => "FAQ's",
            'faq.home_heading' => 'Frequently Asked Questions',
            'faq.home_background' => 'images/faq-bg.jpg',
        ];
    }

    /** @return array{home_visible: bool, home_eyebrow: string, home_heading: string, home_background: string} */
    public static function content(): array
    {
        $v = Setting::many(self::defaults());

        return [
            'home_visible' => $v['faq.home_visible'] === '1',
            'home_eyebrow' => $v['faq.home_eyebrow'],
            'home_heading' => $v['faq.home_heading'],
            'home_background' => $v['faq.home_background'],
        ];
    }
}
