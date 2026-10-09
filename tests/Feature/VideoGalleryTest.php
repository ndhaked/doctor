<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\GalleryVideo;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class VideoGalleryTest extends TestCase
{
    use RefreshDatabase;

    protected function superAdmin(): Admin
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $admin = Admin::factory()->create();
        $admin->assignRole('Super Admin');

        return $admin;
    }

    protected function video(string $title, string $id = 'dQw4w9WgXcQ', bool $active = true): GalleryVideo
    {
        return GalleryVideo::create([
            'title' => $title, 'youtube_url' => 'https://youtu.be/'.$id, 'youtube_id' => $id,
            'is_active' => $active, 'sort_order' => GalleryVideo::count() + 1,
        ]);
    }

    public function test_extracts_the_id_from_youtube_links_only(): void
    {
        foreach ([
            'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'https://youtube.com/watch?v=dQw4w9WgXcQ&t=10s',
            'https://m.youtube.com/watch?v=dQw4w9WgXcQ',
            'https://youtu.be/dQw4w9WgXcQ?si=abc',
            'https://www.youtube.com/shorts/dQw4w9WgXcQ',
            'https://www.youtube.com/embed/dQw4w9WgXcQ',
            'https://www.youtube.com/live/dQw4w9WgXcQ',
        ] as $url) {
            $this->assertSame('dQw4w9WgXcQ', GalleryVideo::extractYoutubeId($url), $url);
        }

        foreach (['', 'dQw4w9WgXcQ', 'https://vimeo.com/12345678', 'https://example.com/watch?v=dQw4w9WgXcQ', 'https://www.youtube.com/watch?v=short', 'javascript:alert(1)', 'https://www.youtube.com/'] as $bad) {
            $this->assertNull(GalleryVideo::extractYoutubeId($bad), $bad);
        }
    }

    public function test_page_shows_thumbnails_without_loading_the_player(): void
    {
        $this->video('Covid vaccines video', 'aaaaaaaaaaa');
        $this->video('Hidden video title', 'bbbbbbbbbbb', active: false);

        $html = $this->get(route('video-gallery'))->assertOk()
            ->assertSee('Covid vaccines video')->assertDontSee('Hidden video title')
            ->assertSee('https://img.youtube.com/vi/aaaaaaaaaaa/hqdefault.jpg', false)
            ->getContent();

        // the player address is only held in a data attribute, never an iframe, until clicked
        $this->assertStringNotContainsString('<iframe src', $html);
    }

    public function test_empty_page_and_menu_links(): void
    {
        Setting::put('videos.title', 'Our Videos');

        $this->get(route('video-gallery'))->assertOk()->assertSee('Our Videos')->assertSee('Videos will be added soon.');
        $this->get('/')->assertSee(route('video-gallery'), false);
    }

    public function test_admin_without_permission_is_forbidden(): void
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $admin = Admin::factory()->create();
        $admin->assignRole('Admin');

        $this->actingAs($admin, 'admin')->get(route('admin.videos.index'))->assertForbidden();
    }

    public function test_admin_adds_edits_hides_orders_and_deletes_videos(): void
    {
        $this->actingAs($this->superAdmin(), 'admin');

        $c = Livewire::test('pages::admin.videos.index')
            ->call('create')->call('save')->assertHasErrors(['title', 'youtube_url'])
            ->set('title', 'Bad link')->set('youtube_url', 'https://vimeo.com/123')->call('save')->assertHasErrors(['youtube_url'])
            ->set('title', 'First')->set('youtube_url', 'https://youtu.be/aaaaaaaaaaa')->call('save')->assertHasNoErrors()
            ->call('create')->set('title', 'Second')->set('youtube_url', 'https://www.youtube.com/watch?v=bbbbbbbbbbb')->call('save');

        $first = GalleryVideo::where('title', 'First')->first();
        $second = GalleryVideo::where('title', 'Second')->first();
        $this->assertSame('aaaaaaaaaaa', $first->youtube_id);
        $this->assertSame('bbbbbbbbbbb', $second->youtube_id);

        $c->call('edit', $first->id)->set('title', 'First edited')->set('youtube_url', 'https://youtu.be/ccccccccccc')->call('save')
            ->call('toggle', $second->id)
            ->call('move', $second->id, 'up');

        $this->assertSame('ccccccccccc', $first->fresh()->youtube_id);
        $this->assertSame('First edited', $first->fresh()->title);
        $this->assertFalse($second->fresh()->is_active);
        $this->assertTrue($second->fresh()->sort_order < $first->fresh()->sort_order);

        $c->call('delete', $second->id);
        $this->assertDatabaseMissing('gallery_videos', ['id' => $second->id]);
    }

    public function test_page_texts_can_be_saved(): void
    {
        $this->actingAs($this->superAdmin(), 'admin');

        Livewire::test('pages::admin.videos.index')
            ->set('page_title', '')->call('savePage')->assertHasErrors(['page_title'])
            ->set('page_title', 'Watch & Learn')->set('page_intro', 'Short clips')->call('savePage')->assertHasNoErrors();

        $this->get(route('video-gallery'))->assertSee('Short clips');
    }
}
