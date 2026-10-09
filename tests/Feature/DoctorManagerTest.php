<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class DoctorManagerTest extends TestCase
{
    use RefreshDatabase;

    protected function superAdmin(): Admin
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $admin = Admin::factory()->create();
        $admin->assignRole('Super Admin');

        return $admin;
    }

    public function test_admin_without_permission_cannot_open_the_doctor_manager(): void
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $admin = Admin::factory()->create();
        $admin->assignRole('Admin');

        $this->actingAs($admin, 'admin')->get(route('admin.doctor.index'))->assertForbidden();
    }

    public function test_super_admin_sees_the_current_content(): void
    {
        $this->actingAs($this->superAdmin(), 'admin')
            ->get(route('admin.doctor.index'))
            ->assertOk()
            ->assertSee('Doctor Profile')
            ->assertSee('Show this block on the home page')
            ->assertSee('Dr. Aditya Sharma');
    }

    public function test_the_block_uses_the_original_content_by_default(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Meet Our Expert General Physician In Jaipur')
            ->assertSee('Consultant Physician &amp; Diabetologist', false)
            ->assertSee('Of Experience')
            ->assertSee('Hypertension (high BP)')
            ->assertSee('images/about-doctor.webp', false);
    }

    public function test_admin_can_edit_the_text_badge_list_and_button(): void
    {
        Livewire::actingAs($this->superAdmin(), 'admin')
            ->test('pages::admin.doctor.index')
            ->set('eyebrow', 'Your doctor')
            ->set('name', 'Dr. Priya Verma')
            ->set('qualifications', "MBBS, MD\nCardiologist")
            ->set('description', "First paragraph.\n\nSecond paragraph.")
            ->set('badge_number', '20+')
            ->set('badge_label', "Years of\nService")
            ->set('diseases_intro', 'She treats:')
            ->set('diseases', "Heart disease\nBlood pressure")
            ->set('button_text', 'Read more')
            ->set('button_url', '/faq')
            ->call('save')
            ->assertHasNoErrors();

        $response = $this->get(route('home'))
            ->assertOk()
            ->assertSee('Your doctor')
            ->assertSee('Dr. Priya Verma')
            ->assertSee('Cardiologist')
            ->assertSee('First paragraph.')
            ->assertSee('Second paragraph.')
            ->assertSee('20+')
            ->assertSee('She treats:')
            ->assertSee('Heart disease')
            ->assertSee('Blood pressure')
            ->assertSee('Read more')
            ->assertDontSee('Meet Our Expert General Physician');

        $this->assertStringContainsString('href="/faq"', $response->getContent());
    }

    public function test_admin_can_hide_and_show_the_block(): void
    {
        $component = Livewire::actingAs($this->superAdmin(), 'admin')->test('pages::admin.doctor.index');

        $component->set('visible', false)->call('save')->assertHasNoErrors();
        $this->assertSame('0', Setting::get('doctor.visible'));
        $this->get(route('home'))->assertOk()->assertDontSee('Meet Our Expert General Physician');

        $component->set('visible', true)->call('save');
        $this->get(route('home'))->assertOk()->assertSee('Meet Our Expert General Physician');
    }

    public function test_clearing_optional_parts_hides_them(): void
    {
        $component = Livewire::actingAs($this->superAdmin(), 'admin')->test('pages::admin.doctor.index');

        // a unique list item and button, so the check cannot match other parts of the home page
        $component->set('diseases', 'Zebra Syndrome')->set('button_text', 'Zebra Button')->call('save');
        $this->get(route('home'))->assertOk()->assertSee('Zebra Syndrome')->assertSee('Zebra Button');

        $component
            ->set('badge_number', '')
            ->set('badge_label', '')
            ->set('diseases_intro', '')
            ->set('diseases', '')
            ->set('button_text', '')
            ->call('save')
            ->assertHasNoErrors();

        $this->get(route('home'))
            ->assertOk()
            ->assertDontSee('Zebra Syndrome')
            ->assertDontSee('Zebra Button')
            ->assertDontSee('Of Experience')
            ->assertDontSee('provides the best treatment for the following diseases');
    }

    public function test_the_doctor_name_cannot_be_empty(): void
    {
        Livewire::actingAs($this->superAdmin(), 'admin')
            ->test('pages::admin.doctor.index')
            ->set('name', '')
            ->call('save')
            ->assertHasErrors(['name']);
    }

    public function test_admin_can_replace_remove_and_restore_the_photo(): void
    {
        Storage::fake('uploads');

        $component = Livewire::actingAs($this->superAdmin(), 'admin')->test('pages::admin.doctor.index');

        $component->set('photoFile', UploadedFile::fake()->image('doctor.jpg', 800, 1000))
            ->call('save')
            ->assertHasNoErrors();

        $path = Setting::get('doctor.photo');
        $this->assertStringStartsWith('uploads/doctor/', $path);
        Storage::disk('uploads')->assertExists(substr($path, strlen('uploads/')));

        $component->call('removePhoto');
        $this->assertSame('', Setting::get('doctor.photo'));
        Storage::disk('uploads')->assertMissing(substr($path, strlen('uploads/')));
        $this->get(route('home'))->assertOk()->assertDontSee('images/about-doctor.webp', false);

        $component->call('restorePhoto');
        $this->assertSame('images/about-doctor.webp', Setting::get('doctor.photo'));
        $this->assertFileExists(public_path('images/about-doctor.webp'));
    }
}
