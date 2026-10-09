<?php

namespace Database\Seeders;

use App\Models\BlogPost;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class BlogPostSeeder extends Seeder
{
    /** Seeds the starter posts from config/blog.php. Only runs while the table is empty. */
    public function run(): void
    {
        if (BlogPost::query()->exists()) {
            return;
        }

        foreach (config('blog.posts') as $post) {
            BlogPost::create([
                'title' => $post['title'],
                'slug' => BlogPost::uniqueSlug($post['title']),
                'excerpt' => $post['excerpt'],
                'content' => $post['content'] ?? null,
                'image' => 'images/blog/'.$post['image'].'.webp',
                'published_at' => Carbon::parse($post['date'])->toDateString(),
                'is_active' => true,
            ]);
        }
    }
}
