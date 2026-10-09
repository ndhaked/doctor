<?php

namespace App\Support;

use App\Models\Setting;

/** Heading texts of the Video Gallery page (Admin > Video Gallery). */
class VideoPageSection
{
    public static function defaults(): array
    {
        return [
            'videos.title' => 'Video Gallery',
            'videos.intro' => 'Watch health advice and awareness videos by Dr. Aditya Sharma.',
        ];
    }

    public static function content(): array
    {
        $v = Setting::many(self::defaults());

        return ['title' => $v['videos.title'], 'intro' => $v['videos.intro']];
    }
}
