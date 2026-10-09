<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class IntroManagerTest extends TestCase
{
    use RefreshDatabase;

    protected function superAdmin(): Admin
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $admin = Admin::factory()->create();
        $admin->assignRole('Super Admin');

        return $admin;
    }

    public function test_admin_without_permission_cannot_open_the_intro_manager(): void
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $admin = Admin::factory()->create();
        $admin->assignRole('Admin');

        $this->actingAs($admin, 'admin')->get(route('admin.intro.index'))->assertForbidden();
    }

    public function test_super_admin_sees_the_current_content(): void
    {
        $this->actingAs($this->superAdmin(), 'admin')
            ->get(route('admin.intro.index'))
            ->assertOk()
            ->assertSee('Appointment Intro')
            ->assertSee('Show this block on the home page')
            ->assertSee('Book an appointment With Dr. Aditya Sharma');
    }

    public function test_the_block_is_shown_on_the_home_page_by_default(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Book an appointment With Dr. Aditya Sharma')
            ->assertSee('images/clinic/photo-2.webp', false);
    }

    public function test_admin_can_edit_the_heading_and_paragraphs(): void
    {
        Livewire::actingAs($this->superAdmin(), 'admin')
            ->test('pages::admin.intro.index')
            ->set('heading', 'Meet your doctor')
            ->set('content', "First paragraph here.\n\nSecond paragraph here.")
            ->call('save')
            ->assertHasNoErrors();

        $response = $this->get(route('home'))->assertOk();

        $response->assertSee('Meet your doctor')->assertSee('First paragraph here.')->assertSee('Second paragraph here.');
        $this->assertSame(2, substr_count($response->getContent(), 'paragraph here.'));
    }

    public function test_admin_can_hide_and_show_the_block(): void
    {
        $component = Livewire::actingAs($this->superAdmin(), 'admin')->test('pages::admin.intro.index');

        $component->set('visible', false)->call('save')->assertHasNoErrors();
        $this->assertSame('0', Setting::get('intro.visible'));
        $this->get(route('home'))->assertOk()->assertDontSee('Book an appointment With Dr. Aditya Sharma');

        $component->set('visible', true)->call('save');
        $this->get(route('home'))->assertOk()->assertSee('Book an appointment With Dr. Aditya Sharma');
    }

    public function test_the_heading_cannot_be_empty(): void
    {
        Livewire::actingAs($this->superAdmin(), 'admin')
            ->test('pages::admin.intro.index')
            ->set('heading', '')
            ->call('save')
            ->assertHasErrors(['heading']);
    }

    public function test_admin_can_replace_remove_and_restore_the_photo(): void
    {
        Storage::fake('uploads');

        $component = Livewire::actingAs($this->superAdmin(), 'admin')->test('pages::admin.intro.index');

        $component->set('imageFile', UploadedFile::fake()->image('doctor.jpg', 900, 600))
            ->call('save')
            ->assertHasNoErrors();

        $path = Setting::get('intro.image');
        $this->assertStringStartsWith('uploads/intro/', $path);
        Storage::disk('uploads')->assertExists(substr($path, strlen('uploads/')));

        $component->call('removeImage');
        $this->assertSame('', Setting::get('intro.image'));
        Storage::disk('uploads')->assertMissing(substr($path, strlen('uploads/')));
        $this->get(route('home'))->assertOk()->assertDontSee('alt="Book an appointment With Dr. Aditya Sharma"', false);

        $component->call('restoreImage');
        $this->assertSame('images/clinic/photo-2.webp', Setting::get('intro.image'));
        $this->assertFileExists(public_path('images/clinic/photo-2.webp'));
    }
}
