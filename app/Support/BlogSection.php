<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Editable texts and settings for everything blog-related on the public site:
 * the home page blog block, the Health Tips page banner and the "Recent News" panel on post pages.
 * Values live in the `settings` table; anything not saved yet falls back to these defaults.
 */
class BlogSection
{
    public const HOME_COUNT_MAX = 24;
    public const RECENT_COUNT_MAX = 12;

    public static function defaults(): array
    {
        return [
            // Home page block
            'blog.home_visible' => '1',
            'blog.home_eyebrow' => 'Our Blog',
            'blog.home_heading' => 'Our Latest health tips blogs by Dr. Aditya Sharma on various health conditions and their treatment options.',
            'blog.home_button_text' => 'View All',
            'blog.home_count' => '8',

            // Health Tips page banner
            'blog.page_title' => 'Blogs',
            'blog.page_intro' => 'Read all the latest health tips blogs on infectious diseases provided by Dr. Aditya Sharma (Consultant Physician & Diabetologist).',

            // "Recent News" panel on a post page
            'blog.recent_heading' => 'Recent News',
            'blog.recent_count' => '4',
        ];
    }

    /**
     * @return array{home_visible: bool, home_eyebrow: string, home_heading: string, home_button_text: string, home_count: int, page_title: string, page_intro: string, recent_heading: string, recent_count: int}
     */
    public static function content(): array
    {
        $v = Setting::many(self::defaults());

        return [
            'home_visible' => $v['blog.home_visible'] === '1',
            'home_eyebrow' => $v['blog.home_eyebrow'],
            'home_heading' => $v['blog.home_heading'],
            'home_button_text' => $v['blog.home_button_text'],
            'home_count' => max(1, min(self::HOME_COUNT_MAX, (int) $v['blog.home_count'])),
            'page_title' => $v['blog.page_title'],
            'page_intro' => $v['blog.page_intro'],
            'recent_heading' => $v['blog.recent_heading'],
            'recent_count' => max(1, min(self::RECENT_COUNT_MAX, (int) $v['blog.recent_count'])),
        ];
    }
}
