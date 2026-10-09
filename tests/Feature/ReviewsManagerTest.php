<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Review;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ReviewsManagerTest extends TestCase
{
    use RefreshDatabase;

    protected function superAdmin(): Admin
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $admin = Admin::factory()->create();
        $admin->assignRole('Super Admin');

        return $admin;
    }

    protected function review(array $attributes = []): Review
    {
        return Review::create($attributes + [
            'name' => 'Asha Patel',
            'text' => 'Wonderful care and a very kind doctor.',
            'rating' => 5,
            'is_active' => true,
            'sort_order' => 1,
        ]);
    }

    public function test_admin_without_permission_cannot_open_the_reviews_manager(): void
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $admin = Admin::factory()->create();
        $admin->assignRole('Admin');

        $this->actingAs($admin, 'admin')->get(route('admin.reviews.index'))->assertForbidden();
    }

    public function test_super_admin_sees_the_settings_and_reviews(): void
    {
        $this->review(['name' => 'Ravi Kumar']);

        $this->actingAs($this->superAdmin(), 'admin')
            ->get(route('admin.reviews.index'))
            ->assertOk()
            ->assertSee('Reviews')
            ->assertSee('Show this section on the website')
            ->assertSee('What Our Patients Say')
            ->assertSee('Ravi Kumar');
    }

    public function test_the_section_uses_the_original_wording_on_every_page_that_has_it(): void
    {
        $this->review();

        foreach (['home', 'about', 'contact'] as $route) {
            $this->get(route($route))
                ->assertOk()
                ->assertSee('Our Testimonial')
                ->assertSee('What Our Patients Say')
                ->assertSee('Google reviews')
                ->assertSee('900+ Reviews')
                ->assertSee('View All Review')
                ->assertSee('Asha Patel');
        }
    }

    public function test_admin_can_edit_the_section_texts_for_every_page(): void
    {
        $this->review();

        Livewire::actingAs($this->superAdmin(), 'admin')
            ->test('pages::admin.reviews.index')
            ->set('eyebrow', 'Kind words')
            ->set('heading', 'Loved by families')
            ->set('intro', 'Real feedback from real patients.')
            ->set('google_label', 'Rated on Google')
            ->set('rating', '4.8')
            ->set('count_text', '1,200 Reviews')
            ->set('button_text', 'Read them all')
            ->set('button_url', 'https://g.page/our-clinic')
            ->call('saveSection')
            ->assertHasNoErrors();

        foreach (['home', 'about', 'contact'] as $route) {
            $response = $this->get(route($route))
                ->assertOk()
                ->assertSee('Kind words')
                ->assertSee('Loved by families')
                ->assertSee('Real feedback from real patients.')
                ->assertSee('Rated on Google')
                ->assertSee('4.8')
                ->assertSee('1,200 Reviews')
                ->assertSee('Read them all')
                ->assertDontSee('What Our Patients Say');

            $this->assertStringContainsString('href="https://g.page/our-clinic"', $response->getContent());
        }
    }

    public function test_the_section_can_be_hidden_on_every_page(): void
    {
        $this->review();

        $component = Livewire::actingAs($this->superAdmin(), 'admin')->test('pages::admin.reviews.index');

        $component->set('visible', false)->call('saveSection')->assertHasNoErrors();
        $this->assertSame('0', Setting::get('reviews.visible'));

        foreach (['home', 'about', 'contact'] as $route) {
            $this->get(route($route))->assertOk()->assertDontSee('What Our Patients Say')->assertDontSee('id="testimonials"', false);
        }

        $component->set('visible', true)->call('saveSection');
        $this->get(route('home'))->assertOk()->assertSee('What Our Patients Say');
    }

    public function test_the_rating_line_and_button_can_be_hidden(): void
    {
        $this->review();

        Livewire::actingAs($this->superAdmin(), 'admin')
            ->test('pages::admin.reviews.index')
            ->set('google_label', '')
            ->set('rating', '')
            ->set('count_text', '')
            ->set('button_text', '')
            ->call('saveSection')
            ->assertHasNoErrors();

        $this->get(route('home'))
            ->assertOk()
            ->assertDontSee('Google reviews')
            ->assertDontSee('900+ Reviews')
            ->assertDontSee('View All Review');
    }

    public function test_section_values_are_validated(): void
    {
        Livewire::actingAs($this->superAdmin(), 'admin')
            ->test('pages::admin.reviews.index')
            ->set('heading', '')
            ->set('rating', '9')
            ->call('saveSection')
            ->assertHasErrors(['heading', 'rating']);

        Livewire::actingAs($this->superAdmin(), 'admin')
            ->test('pages::admin.reviews.index')
            ->set('rating', 'abc')
            ->call('saveSection')
            ->assertHasErrors(['rating']);
    }

    public function test_admin_can_manage_reviews(): void
    {
        $component = Livewire::actingAs($this->superAdmin(), 'admin')->test('pages::admin.reviews.index');

        $component->call('create')
            ->set('name', 'Meera Shah')
            ->set('text', 'Very professional and caring team.')
            ->set('stars', 4)
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('showModal', false);

        $new = Review::firstWhere('name', 'Meera Shah');
        $this->assertNotNull($new);
        $this->assertSame(4, $new->rating);

        $other = $this->review(['name' => 'Asha Patel', 'sort_order' => 1]);
        $new->update(['sort_order' => 2]);

        $component->call('move', $new->id, 'up');
        $this->assertSame(1, $new->fresh()->sort_order);
        $this->assertSame(2, $other->fresh()->sort_order);

        $component->call('edit', $new->id)->assertSet('stars', 4)->set('name', 'Meera S.')->call('save');
        $this->assertSame('Meera S.', $new->fresh()->name);

        $component->call('toggle', $new->id);
        $this->assertFalse($new->fresh()->is_active);
        $this->get(route('home'))->assertOk()->assertDontSee('Meera S.');

        $component->call('delete', $new->id);
        $this->assertNull(Review::find($new->id));
    }

    public function test_a_review_needs_a_name_text_and_a_valid_star_count(): void
    {
        Livewire::actingAs($this->superAdmin(), 'admin')
            ->test('pages::admin.reviews.index')
            ->call('create')
            ->set('stars', 9)
            ->call('save')
            ->assertHasErrors(['name', 'text', 'stars']);
    }

    public function test_each_review_shows_its_own_star_count_and_hidden_reviews_are_left_out(): void
    {
        $this->review(['name' => 'Three Star Person', 'rating' => 3, 'sort_order' => 1]);
        $this->review(['name' => 'Hidden Person', 'is_active' => false, 'sort_order' => 2]);

        $html = $this->get(route('home'))->assertOk()->assertSee('Three Star Person')->assertDontSee('Hidden Person')->getContent();

        $start = strpos($html, 'Three Star Person');
        $card = substr($html, $start, strpos($html, 'data-more', $start) - $start);
        $this->assertSame(3, substr_count($card, '<svg'));
    }

    public function test_the_slider_is_left_out_when_there_are_no_reviews(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('What Our Patients Say')
            ->assertDontSee('data-review-slider', false);
    }
}
