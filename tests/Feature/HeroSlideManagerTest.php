<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\HeroSlide;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class HeroSlideManagerTest extends TestCase
{
    use RefreshDatabase;

    protected function superAdmin(): Admin
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $admin = Admin::factory()->create();
        $admin->assignRole('Super Admin');

        return $admin;
    }

    protected function slide(array $attributes = []): HeroSlide
    {
        return HeroSlide::create($attributes + [
            'image' => 'images/slider-1.webp',
            'title' => 'Slide',
            'cards' => [['icon' => 'droplet', 'title' => 'Diabetes']],
            'cta_text' => 'Book',
            'cta_action' => 'appointment',
            'is_active' => true,
            'sort_order' => 1,
        ]);
    }

    public function test_admin_without_permission_cannot_open_the_slider_manager(): void
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $admin = Admin::factory()->create();
        $admin->assignRole('Admin');

        $this->actingAs($admin, 'admin')->get(route('admin.slides.index'))->assertForbidden();
    }

    public function test_super_admin_can_open_the_slider_manager(): void
    {
        $this->slide(['title' => 'Trusted Family Physician']);

        $this->actingAs($this->superAdmin(), 'admin')
            ->get(route('admin.slides.index'))
            ->assertOk()
            ->assertSee('Hero Slider')
            ->assertSee('Trusted Family Physician');
    }

    public function test_home_page_shows_only_active_slides_in_order(): void
    {
        $this->slide(['title' => 'Second slide', 'sort_order' => 2]);
        $this->slide(['title' => 'First slide', 'sort_order' => 1]);
        $this->slide(['title' => 'Hidden slide', 'sort_order' => 3, 'is_active' => false]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSeeInOrder(['First slide', 'Second slide'])
            ->assertDontSee('Hidden slide');
    }

    public function test_home_page_renders_without_any_slides(): void
    {
        $this->get(route('home'))->assertOk();
    }

    public function test_admin_can_add_a_slide_with_an_uploaded_image(): void
    {
        Storage::fake('uploads');

        Livewire::actingAs($this->superAdmin(), 'admin')
            ->test('pages::admin.slides.index')
            ->call('create')
            ->set('title', 'New Slide')
            ->set('badge', 'Care')
            ->set('cards.0.title', 'Diabetes')
            ->set('imageFile', UploadedFile::fake()->image('bg.jpg', 1600, 800))
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('showModal', false);

        $slide = HeroSlide::firstWhere('title', 'New Slide');

        $this->assertNotNull($slide);
        $this->assertStringStartsWith('uploads/slides/', $slide->image);
        $this->assertSame([['icon' => 'droplet', 'title' => 'Diabetes']], $slide->cards);
        Storage::disk('uploads')->assertExists(substr($slide->image, strlen('uploads/')));
    }

    public function test_a_new_slide_needs_a_title_and_an_image(): void
    {
        Livewire::actingAs($this->superAdmin(), 'admin')
            ->test('pages::admin.slides.index')
            ->call('create')
            ->call('save')
            ->assertHasErrors(['title', 'imageFile']);
    }

    public function test_editing_keeps_the_existing_image_when_no_new_one_is_chosen(): void
    {
        $slide = $this->slide(['title' => 'Old title']);

        Livewire::actingAs($this->superAdmin(), 'admin')
            ->test('pages::admin.slides.index')
            ->call('edit', $slide->id)
            ->assertSet('title', 'Old title')
            ->set('title', 'New title')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('New title', $slide->fresh()->title);
        $this->assertSame('images/slider-1.webp', $slide->fresh()->image);
    }

    public function test_admin_can_toggle_reorder_and_delete_slides(): void
    {
        $a = $this->slide(['title' => 'A', 'sort_order' => 1]);
        $b = $this->slide(['title' => 'B', 'sort_order' => 2]);

        $component = Livewire::actingAs($this->superAdmin(), 'admin')->test('pages::admin.slides.index');

        $component->call('toggle', $a->id);
        $this->assertFalse($a->fresh()->is_active);

        $component->call('move', $b->id, 'up');
        $this->assertSame(1, $b->fresh()->sort_order);
        $this->assertSame(2, $a->fresh()->sort_order);

        $component->call('delete', $a->id);
        $this->assertNull(HeroSlide::find($a->id));
        // bundled images are never removed from disk
        $this->assertFileExists(public_path('images/slider-1.webp'));
    }

    public function test_custom_button_link_is_required_for_the_custom_action(): void
    {
        Storage::fake('uploads');

        Livewire::actingAs($this->superAdmin(), 'admin')
            ->test('pages::admin.slides.index')
            ->call('create')
            ->set('title', 'With link')
            ->set('cards', [])
            ->set('cta_text', 'Learn more')
            ->set('cta_action', 'custom')
            ->set('imageFile', UploadedFile::fake()->image('bg.jpg'))
            ->call('save')
            ->assertHasErrors(['cta_url']);
    }
}
