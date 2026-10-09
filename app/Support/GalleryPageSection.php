<?php

namespace App\Support;

use App\Models\Setting;

/** Heading texts of the Image Gallery page (Admin > Image Gallery). */
class GalleryPageSection
{
    public static function defaults(): array
    {
        return [
            'gallery.title' => 'Image Gallery',
            'gallery.intro' => "View all the images of Dr. Aditya Sharma's awards, clinic, and other successful case studies.",
        ];
    }

    public static function content(): array
    {
        $v = Setting::many(self::defaults());

        return ['title' => $v['gallery.title'], 'intro' => $v['gallery.intro']];
    }
}
