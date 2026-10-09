<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Editable texts of the "Why Choose?" block on the home page (the accordion rows are in `why_choose_items`).
 * Values live in the `settings` table; anything not saved yet falls back to these defaults.
 */
class WhyChooseSection
{
    public static function defaults(): array
    {
        return [
            'whychoose.visible' => '1',
            'whychoose.heading' => 'Why Choose?',
            'whychoose.subheading' => 'Why Choose Dr. Aditya Sharma (General Physician in Jaipur)?',
            'whychoose.content' => 'Dr. Aditya Sharma, a distinguished general physician in Jaipur, is the epitome of medical excellence and compassionate care. Renowned for his unwavering commitment to patient well-being, Dr. Sharma combines profound medical expertise with a personalized approach, ensuring each patient receives tailored treatment. With years of experience and a deep understanding of various medical conditions, he adeptly navigates complex diagnoses and provides comprehensive solutions. Beyond his clinical proficiency, Dr. Sharma fosters a nurturing environment where patients feel heard and supported throughout their healthcare journey. Choosing Dr. Aditya Sharma means entrusting your health to a dedicated professional who prioritizes your holistic well-being above all else.',
        ];
    }

    /** @return array{visible: bool, heading: string, subheading: string, content: string} */
    public static function content(): array
    {
        $values = Setting::many(self::defaults());

        return [
            'visible' => $values['whychoose.visible'] === '1',
            'heading' => $values['whychoose.heading'],
            'subheading' => $values['whychoose.subheading'],
            'content' => $values['whychoose.content'],
        ];
    }
}
