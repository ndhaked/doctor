<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\ClinicTreatment;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ClinicManagerTest extends TestCase
{
    use RefreshDatabase;

    protected function superAdmin(): Admin
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $admin = Admin::factory()->create();
        $admin->assignRole('Super Admin');

        return $admin;
    }

    protected function card(array $attributes = []): ClinicTreatment
    {
        return ClinicTreatment::create($attributes + [
            'title' => 'Diabetes',
            'image' => 'images/clinic/cl-3.png',
            'is_active' => true,
            'sort_order' => 1,
        ]);
    }

    public function test_admin_without_permission_cannot_open_the_clinic_manager(): void
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $admin = Admin::factory()->create();
        $admin->assignRole('Admin');

        $this->actingAs($admin, 'admin')->get(route('admin.clinic.index'))->assertForbidden();
    }

    public function test_super_admin_sees_the_block_settings_and_cards(): void
    {
        $this->card(['title' => 'Thyroid disorders']);

        $this->actingAs($this->superAdmin(), 'admin')
            ->get(route('admin.clinic.index'))
            ->assertOk()
            ->assertSee('About the Clinic')
            ->assertSee('Thyroid disorders')
            ->assertSee('Satisfied clients');
    }

    public function test_home_page_uses_defaults_until_the_block_is_edited(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('10,000+')
            ->assertSee('Satisfied clients')
            ->assertSee('images/clinic/photo-1.webp', false);
    }

    public function test_admin_can_edit_the_block_text_counter_and_button(): void
    {
        Livewire::actingAs($this->superAdmin(), 'admin')
            ->test('pages::admin.clinic.index')
            ->set('eyebrow', 'Our clinic')
            ->set('heading', 'Welcome to our clinic')
            ->set('description', 'We care for families.')
            ->set('stat_number', '25,000+')
            ->set('stat_label', 'Happy patients')
            ->set('button_text', 'Call us')
            ->set('button_url', '/faq')
            ->call('saveContent')
            ->assertHasNoErrors();

        $this->assertSame('Welcome to our clinic', Setting::get('clinic.heading'));

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Welcome to our clinic')
            ->assertSee('25,000+')
            ->assertSee('Happy patients')
            ->assertSee('We care for families.')
            ->assertSee('Call us');
    }

    public function test_the_heading_cannot_be_empty(): void
    {
        Livewire::actingAs($this->superAdmin(), 'admin')
            ->test('pages::admin.clinic.index')
            ->set('heading', '')
            ->call('saveContent')
            ->assertHasErrors(['heading']);
    }

    public function test_clearing_the_counter_and_button_hides_them(): void
    {
        Livewire::actingAs($this->superAdmin(), 'admin')
            ->test('pages::admin.clinic.index')
            ->set('stat_number', '')
            ->set('stat_label', '')
            ->set('button_text', '')
            ->call('saveContent')
            ->assertHasNoErrors();

        $this->get(route('home'))
            ->assertOk()
            ->assertDontSee('10,000+')
            ->assertDontSee('Satisfied clients');
    }

    public function test_admin_can_replace_remove_and_restore_a_photo(): void
    {
        Storage::fake('uploads');

        $component = Livewire::actingAs($this->superAdmin(), 'admin')->test('pages::admin.clinic.index');

        $component->set('photoFiles.1', UploadedFile::fake()->image('new.jpg', 800, 600))
            ->call('saveContent')
            ->assertHasNoErrors();

        $path = Setting::get('clinic.photo_1');
        $this->assertStringStartsWith('uploads/clinic/', $path);
        Storage::disk('uploads')->assertExists(substr($path, strlen('uploads/')));

        $component->call('removePhoto', 1);
        $this->assertSame('', Setting::get('clinic.photo_1'));
        Storage::disk('uploads')->assertMissing(substr($path, strlen('uploads/')));

        $component->call('restorePhoto', 1);
        $this->assertSame('images/clinic/photo-1.webp', Setting::get('clinic.photo_1'));
        // the bundled original is never deleted
        $this->assertFileExists(public_path('images/clinic/photo-1.webp'));
    }

    public function test_admin_can_manage_treatment_cards(): void
    {
        Storage::fake('uploads');

        $component = Livewire::actingAs($this->superAdmin(), 'admin')->test('pages::admin.clinic.index');

        $component->call('create')
            ->set('title', 'Obesity')
            ->set('imageFile', UploadedFile::fake()->image('icon.png', 64, 64))
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('showModal', false);

        $new = ClinicTreatment::firstWhere('title', 'Obesity');
        $this->assertNotNull($new);
        $this->assertStringStartsWith('uploads/clinic/', $new->image);

        $other = $this->card(['title' => 'Asthma', 'sort_order' => 1]);
        $new->update(['sort_order' => 2]);

        $component->call('move', $new->id, 'up');
        $this->assertSame(1, $new->fresh()->sort_order);
        $this->assertSame(2, $other->fresh()->sort_order);

        $component->call('edit', $new->id)->set('title', 'Weight care')->call('save');
        $this->assertSame('Weight care', $new->fresh()->title);

        $component->call('toggle', $new->id);
        $this->assertFalse($new->fresh()->is_active);

        $component->call('delete', $new->id);
        $this->assertNull(ClinicTreatment::find($new->id));
        Storage::disk('uploads')->assertMissing(substr($new->image, strlen('uploads/')));
    }

    public function test_a_card_needs_a_title(): void
    {
        Livewire::actingAs($this->superAdmin(), 'admin')
            ->test('pages::admin.clinic.index')
            ->call('create')
            ->call('save')
            ->assertHasErrors(['title']);
    }

    public function test_home_page_shows_only_visible_cards(): void
    {
        $this->card(['title' => 'Visible Card']);
        $this->card(['title' => 'Hidden Card', 'is_active' => false, 'sort_order' => 2]);

        $this->get(route('home'))->assertOk()->assertSee('Visible Card')->assertDontSee('Hidden Card');
    }
}
