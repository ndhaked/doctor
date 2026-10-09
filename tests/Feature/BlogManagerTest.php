<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\BlogPost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class BlogManagerTest extends TestCase
{
    use RefreshDatabase;

    protected function superAdmin(): Admin
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $admin = Admin::factory()->create();
        $admin->assignRole('Super Admin');

        return $admin;
    }

    protected function makePost(array $attributes = []): BlogPost
    {
        return BlogPost::create($attributes + [
            'title' => 'A post',
            'slug' => 'a-post',
            'excerpt' => 'Short summary',
            'image' => 'images/blog/post-1.webp',
            'published_at' => '2026-01-10',
            'is_active' => true,
        ]);
    }

    public function test_admin_without_permission_cannot_open_the_blog_manager(): void
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $admin = Admin::factory()->create();
        $admin->assignRole('Admin');

        $this->actingAs($admin, 'admin')->get(route('admin.blog.index'))->assertForbidden();
    }

    public function test_super_admin_sees_posts_in_the_manager(): void
    {
        $this->makePost(['title' => 'Managing Blood Pressure']);

        $this->actingAs($this->superAdmin(), 'admin')
            ->get(route('admin.blog.index'))
            ->assertOk()
            ->assertSee('Blog Posts')
            ->assertSee('Managing Blood Pressure');
    }

    public function test_admin_can_add_a_post_with_an_image_and_a_generated_slug(): void
    {
        Storage::fake('uploads');

        Livewire::actingAs($this->superAdmin(), 'admin')
            ->test('pages::admin.blog.index')
            ->call('create')
            ->set('title', 'Winter Asthma Tips')
            ->set('excerpt', 'Stay safe in winter')
            ->set('content', "First paragraph.\n\nSecond paragraph.")
            ->set('imageFile', UploadedFile::fake()->image('banner.jpg', 1200, 628))
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('showModal', false);

        $post = BlogPost::firstWhere('title', 'Winter Asthma Tips');

        $this->assertNotNull($post);
        $this->assertSame('winter-asthma-tips', $post->slug);
        $this->assertStringStartsWith('uploads/blog/', $post->image);
        Storage::disk('uploads')->assertExists(substr($post->image, strlen('uploads/')));
    }

    public function test_slugs_stay_unique(): void
    {
        $this->makePost(['title' => 'Same Title', 'slug' => 'same-title']);

        $this->assertSame('same-title-2', BlogPost::uniqueSlug('Same Title'));
    }

    public function test_a_title_and_date_are_required(): void
    {
        Livewire::actingAs($this->superAdmin(), 'admin')
            ->test('pages::admin.blog.index')
            ->call('create')
            ->set('published_at', '')
            ->call('save')
            ->assertHasErrors(['title', 'published_at']);
    }

    public function test_admin_can_edit_hide_and_delete_a_post(): void
    {
        $post = $this->makePost();

        $component = Livewire::actingAs($this->superAdmin(), 'admin')->test('pages::admin.blog.index');

        $component->call('edit', $post->id)
            ->assertSet('title', 'A post')
            ->set('title', 'Renamed post')
            ->call('save')
            ->assertHasNoErrors();
        $this->assertSame('Renamed post', $post->fresh()->title);
        $this->assertSame('a-post', $post->fresh()->slug); // link is kept when editing

        $component->call('toggle', $post->id);
        $this->assertFalse($post->fresh()->is_active);

        $component->call('delete', $post->id);
        $this->assertNull(BlogPost::find($post->id));
        $this->assertFileExists(public_path('images/blog/post-1.webp')); // bundled image untouched
    }

    public function test_article_text_is_formatted_into_safe_html(): void
    {
        $post = $this->makePost(['content' => "## Big heading\n\n### Small heading\nBody with **bold** and [a link](/faq).\n\n- First point\n- Second point\n\n<script>alert('x')</script>"]);

        $html = (string) $post->formattedContent();

        $this->assertStringContainsString('<h2>Big heading</h2>', $html);
        $this->assertStringContainsString('<h3>Small heading</h3>', $html);
        $this->assertStringContainsString('<strong>bold</strong>', $html);
        $this->assertStringContainsString('<a href="/faq"', $html);
        $this->assertSame(2, substr_count($html, '<li>'));
        // raw markup from the editor is escaped, never rendered
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function test_post_page_shows_author_date_content_and_recent_news(): void
    {
        $this->makePost(['title' => 'Main Article', 'slug' => 'main-article', 'author' => 'Dr. Sharma', 'content' => "## Why it matters\n\nSome text."]);
        $this->makePost(['title' => 'Another Recent Post', 'slug' => 'another-recent-post', 'published_at' => '2026-02-01']);

        $this->get(route('health-tips.show', 'main-article'))
            ->assertOk()
            ->assertSee('Dr. Sharma')
            ->assertSee('January 10, 2026')
            ->assertSee('Why it matters')
            ->assertSee('Recent News')
            ->assertSee('Another Recent Post');
    }

    public function test_blog_texts_default_to_the_original_wording(): void
    {
        $this->makePost();

        $this->get(route('home'))->assertOk()->assertSee('Our Blog')->assertSee('View All');
        $this->get(route('health-tips'))->assertOk()->assertSee('Blogs');
    }

    public function test_admin_can_edit_every_blog_text_and_setting(): void
    {
        $this->makePost(['title' => 'Post One', 'slug' => 'post-one', 'published_at' => '2026-03-01']);
        $this->makePost(['title' => 'Post Two', 'slug' => 'post-two', 'published_at' => '2026-02-01']);
        $this->makePost(['title' => 'Post Three', 'slug' => 'post-three', 'published_at' => '2026-01-01']);

        Livewire::actingAs($this->superAdmin(), 'admin')
            ->test('pages::admin.blog.index')
            ->set('home_eyebrow', 'From the doctor')
            ->set('home_heading', 'Fresh health advice')
            ->set('home_button_text', 'See every post')
            ->set('home_count', 2)
            ->set('page_title', 'Health Library')
            ->set('page_intro', 'All our articles in one place.')
            ->set('recent_heading', 'Latest Reads')
            ->set('recent_count', 1)
            ->call('saveSettings')
            ->assertHasNoErrors();

        // home block: new texts, only the 2 newest posts
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('From the doctor')
            ->assertSee('Fresh health advice')
            ->assertSee('See every post')
            ->assertSee('Post One')
            ->assertSee('Post Two')
            ->assertDontSee('Post Three');

        // list page banner
        $this->get(route('health-tips'))
            ->assertOk()
            ->assertSee('Health Library')
            ->assertSee('All our articles in one place.');

        // post page sidebar: custom heading and a single entry
        $page = $this->get(route('health-tips.show', 'post-one'))->assertOk()->assertSee('Latest Reads');
        $this->assertSame(1, substr_count($page->getContent(), 'Post Two'));
        $page->assertDontSee('Post Three');
    }

    public function test_admin_can_hide_the_home_blog_block_and_its_button(): void
    {
        $this->makePost(['title' => 'Visible Post']);

        $component = Livewire::actingAs($this->superAdmin(), 'admin')->test('pages::admin.blog.index');

        $component->set('home_visible', false)->call('saveSettings')->assertHasNoErrors();
        $this->get(route('home'))->assertOk()->assertDontSee('id="blog"', false);

        $component->set('home_visible', true)->set('home_button_text', '')->call('saveSettings');
        // the section is back, but without the button (the reviews block has its own "View All Review" link)
        $this->get(route('home'))->assertOk()->assertSee('id="blog"', false)->assertDontSee('>View All</a>', false);
    }

    public function test_blog_settings_are_validated(): void
    {
        Livewire::actingAs($this->superAdmin(), 'admin')
            ->test('pages::admin.blog.index')
            ->set('home_heading', '')
            ->set('page_title', '')
            ->set('recent_heading', '')
            ->set('home_count', 0)
            ->set('recent_count', 99)
            ->call('saveSettings')
            ->assertHasErrors(['home_heading', 'page_title', 'recent_heading', 'home_count', 'recent_count']);
    }

    public function test_public_blog_pages_show_only_published_posts(): void
    {
        $this->makePost(['title' => 'Published Post', 'slug' => 'published-post', 'content' => "Hello\n\nWorld"]);
        $this->makePost(['title' => 'Draft Post', 'slug' => 'draft-post', 'is_active' => false]);

        $this->get(route('health-tips'))->assertOk()->assertSee('Published Post')->assertDontSee('Draft Post');
        $this->get(route('home'))->assertOk()->assertSee('Published Post')->assertDontSee('Draft Post');

        $this->get(route('health-tips.show', 'published-post'))->assertOk()->assertSee('Published Post')->assertSee('World');
        $this->get(route('health-tips.show', 'draft-post'))->assertNotFound();
    }
}
