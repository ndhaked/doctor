<?php

namespace Database\Seeders;

use App\Models\Review;
use Illuminate\Database\Seeder;

class ReviewSeeder extends Seeder
{
    /** Seeds the patient reviews that used to be hard-coded in the reviews section. Only runs while the table is empty. */
    public function run(): void
    {
        if (Review::query()->exists()) {
            return;
        }

        $reviews = [
            ['Rohit Verma', 'I had been struggling with high blood sugar for years and consulted many doctors without much relief. After visiting Dr. Sharma, a few tests and a simple diet and medication plan made a visible difference within weeks. Very patient and clear in explaining everything.', 5],
            ['Anita Joshi', 'Very happy with the consultation. The doctor listens carefully, explains the problem in simple language and never rushes. Highly recommended for anyone looking for a good general physician in Jaipur.', 5],
            ['Manish Kumar', 'My father\'s blood pressure was not under control for a long time. With the right guidance and regular follow-ups it is now stable. Thank you doctor and the clinic staff for the caring attitude.', 5],
            ['Sunita Meena', 'Got treated for viral fever and weakness. The doctor diagnosed the issue quickly and I recovered in a few days. Clean clinic and well-managed appointments with no long waiting.', 5],
            ['Deepak Sharma', 'Excellent experience for thyroid and migraine treatment. The doctor takes time to understand the history and the medicines worked very well. Truly a trustworthy physician.', 5],
        ];

        foreach ($reviews as $i => [$name, $text, $rating]) {
            Review::create(['name' => $name, 'text' => $text, 'rating' => $rating, 'sort_order' => $i + 1]);
        }
    }
}
