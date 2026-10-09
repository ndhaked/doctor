<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Editable content of the "Book an appointment with Dr. ..." intro block on the home page.
 * Values live in the `settings` table; anything not saved yet falls back to these defaults.
 */
class IntroSection
{
    public static function defaults(): array
    {
        return [
            'intro.visible' => '1',
            'intro.heading' => 'Book an appointment With Dr. Aditya Sharma',
            'intro.content' => "If you're seeking exceptional medical care, securing an appointment with Dr. Aditya Sharma, renowned as one of the best physicians in Jaipur, is a prudent choice. Dr. Sharma is esteemed for his profound expertise and compassionate approach towards patient care. With years of experience in internal medicine, he has garnered a reputation for his diagnostic acumen and comprehensive treatment strategies.\n\nAs one of the best physicians in Jaipur, Dr. Sharma ensures that each patient receives optimal care, addressing concerns with empathy and expertise. His clinic offers a welcoming environment where patients feel valued and understood. Whether it's a routine check-up or a complex health issue, Dr. Sharma's commitment to excellence in healthcare makes him a trusted choice among individuals seeking the highest quality medical consultation in Jaipur. To schedule an appointment, contact the clinic today for a consultation that prioritizes your health and well-being.",
            'intro.image' => 'images/clinic/photo-2.webp',
        ];
    }

    /** @return array{visible: bool, heading: string, paragraphs: array<int, string>, content: string, image: string} */
    public static function content(): array
    {
        $values = Setting::many(self::defaults());

        $paragraphs = array_values(array_filter(
            array_map('trim', preg_split('/\R{2,}/', $values['intro.content'])),
            fn ($p) => $p !== ''
        ));

        return [
            'visible' => $values['intro.visible'] === '1',
            'heading' => $values['intro.heading'],
            'paragraphs' => $paragraphs,
            'content' => $values['intro.content'],
            'image' => $values['intro.image'],
        ];
    }
}
