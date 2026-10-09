<?php

namespace Database\Seeders;

use App\Models\GalleryGroup;
use Illuminate\Database\Seeder;

class GallerySeeder extends Seeder
{
    /** Starter groups built from the images bundled with the site. Only runs while the gallery is empty. */
    public function run(): void
    {
        if (GalleryGroup::query()->exists()) {
            return;
        }

        $groups = [
            'Clinic' => [['hospital.webp', 'Our clinic'], ['clinic-poster.webp', 'Clinic reception'], ['director.webp', 'Dr. Aditya Sharma']],
            'Awards' => [['about-doctor.webp', 'Award ceremony'], ['blog/post-1.webp', 'Recognition'], ['blog/post-2.webp', 'Felicitation']],
            'Case Studies' => [['blog/post-3.webp', 'Case study'], ['blog/post-4.webp', 'Patient recovery'], ['blog/post-5.webp', 'Treatment outcome'], ['blog/post-6.webp', 'Follow-up care']],
        ];

        $order = 0;

        foreach ($groups as $title => $images) {
            $group = GalleryGroup::create(['title' => $title, 'sort_order' => ++$order]);

            foreach ($images as $i => [$file, $caption]) {
                $group->images()->create(['image' => 'images/'.$file, 'caption' => $caption, 'sort_order' => $i + 1]);
            }
        }
    }
}
