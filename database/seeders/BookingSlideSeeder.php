<?php

namespace Database\Seeders;

use App\Models\BookingSlide;
use Illuminate\Database\Seeder;

class BookingSlideSeeder extends Seeder
{
    /** Seeds the pictures that used to be hard-coded in the booking section. Only runs while the table is empty. */
    public function run(): void
    {
        if (BookingSlide::query()->exists()) {
            return;
        }

        foreach (['clinic-poster.webp', 'clinic/photo-1.webp', 'clinic/photo-2.webp', 'clinic/photo-3.webp'] as $i => $file) {
            BookingSlide::create(['image' => 'images/'.$file, 'alt' => 'Clinic information', 'sort_order' => $i + 1]);
        }
    }
}
