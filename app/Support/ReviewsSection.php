<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Texts of the "What Our Patients Say" reviews section (shown on several pages).
 * Edited once in the admin (Reviews) and read everywhere through the `$reviewsSection` view variable.
 * Values live in the `settings` table; anything not saved yet falls back to these defaults.
 */
class ReviewsSection
{
    public static function defaults(): array
    {
        return [
            'reviews.visible' => '1',
            'reviews.eyebrow' => 'Our Testimonial',
            'reviews.heading' => 'What Our Patients Say',
            'reviews.intro' => 'Read out patient reviews and testimonial to trust our services and consultation.',
            'reviews.google_label' => 'Google reviews',
            'reviews.rating' => '5.0',
            'reviews.count_text' => '900+ Reviews',
            'reviews.button_text' => 'View All Review',
            'reviews.button_url' => '#',
        ];
    }

    /** @return array<string, mixed> */
    public static function content(): array
    {
        $v = Setting::many(self::defaults());

        return [
            'visible' => $v['reviews.visible'] === '1',
            'eyebrow' => $v['reviews.eyebrow'],
            'heading' => $v['reviews.heading'],
            'intro' => $v['reviews.intro'],
            'google_label' => $v['reviews.google_label'],
            'rating' => $v['reviews.rating'],
            'stars' => max(0, min(5, (int) round((float) $v['reviews.rating']))),
            'count_text' => $v['reviews.count_text'],
            'button_text' => $v['reviews.button_text'],
            'button_url' => $v['reviews.button_url'] !== '' ? $v['reviews.button_url'] : '#',
        ];
    }
}
