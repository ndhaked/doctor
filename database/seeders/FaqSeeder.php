<?php

namespace Database\Seeders;

use App\Models\FaqGroup;
use Illuminate\Database\Seeder;

class FaqSeeder extends Seeder
{
    /**
     * Seeds the FAQs page groups (config/faq.php) and the home page FAQ block (config/home_faq.php).
     * Only runs while there are no groups, so it never overwrites admin changes.
     */
    public function run(): void
    {
        if (FaqGroup::query()->exists()) {
            return;
        }

        $order = 0;

        foreach (config('faq.groups') as $group) {
            $model = FaqGroup::create([
                'title' => $group['title'],
                'placement' => 'faq_page',
                'icon' => 'images/faq/'.$group['icon'],
                'sort_order' => ++$order,
            ]);

            foreach ($group['items'] as $i => $item) {
                $model->items()->create(['question' => $item['q'], 'answer' => $item['a'], 'sort_order' => $i + 1]);
            }
        }

        $home = FaqGroup::create(['title' => 'Home page FAQs', 'placement' => 'home', 'sort_order' => ++$order]);

        foreach (config('home_faq') as $i => $item) {
            $home->items()->create(['question' => $item['q'], 'answer' => $item['a'], 'sort_order' => $i + 1]);
        }
    }
}
