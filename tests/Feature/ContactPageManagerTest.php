<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ContactPageManagerTest extends TestCase
{
    use RefreshDatabase;

    protected function superAdmin(): Admin
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $admin = Admin::factory()->create();
        $admin->assignRole('Super Admin');

        return $admin;
    }

    public function test_admin_without_permission_cannot_open_the_contact_page_manager(): void
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $admin = Admin::factory()->create();
        $admin->assignRole('Admin');

        $this->actingAs($admin, 'admin')->get(route('admin.contact-page.index'))->assertForbidden();
    }

    public function test_super_admin_sees_the_current_texts(): void
    {
        $this->actingAs($this->superAdmin(), 'admin')
            ->get(route('admin.contact-page.index'))
            ->assertOk()
            ->assertSee('Contact Page')
            ->assertSee('Get In Touch!')
            ->assertSee('Have You Any Queries?');
    }

    public function test_the_page_uses_the_original_content_by_default(): void
    {
        $this->get(route('contact'))
            ->assertOk()
            ->assertSee('Contact Us')
            ->assertSee('Looking for the best physician in Jaipur?')
            ->assertSee('Get In Touch!')
            ->assertSee('Opening Hours')
            ->assertSee('Clinic Address')
            ->assertSee('Have You Any Queries?')
            ->assertSee('Send Now')
            ->assertSee('Dr. Aditya Sharma')
            ->assertSee('images/contact/doctor.jpg', false)
            ->assertSee('google.com/maps', false);
    }

    public function test_admin_can_edit_every_text(): void
    {
        Livewire::actingAs($this->superAdmin(), 'admin')
            ->test('pages::admin.contact-page.index')
            ->set('title', 'Reach Us')
            ->set('intro', 'We are happy to hear from you.')
            ->set('info_eyebrow', 'Find us')
            ->set('info_heading', 'Visit or call')
            ->set('info_text', 'Here is how to reach the clinic.')
            ->set('phone_title', 'Call us')
            ->set('hours_title', 'Clinic Hours')
            ->set('address_title', 'Where we are')
            ->set('name', 'Dr. Priya Verma')
            ->set('qualifications', "MBBS, MD\nCardiologist")
            ->set('form_heading', 'Send a message')
            ->set('form_text', 'We will reply soon.')
            ->set('form_button', 'Submit message')
            ->call('save')
            ->assertHasNoErrors();

        $this->get(route('contact'))
            ->assertOk()
            ->assertSee('Reach Us')
            ->assertSee('We are happy to hear from you.')
            ->assertSee('Find us')
            ->assertSee('Visit or call')
            ->assertSee('Here is how to reach the clinic.')
            ->assertSee('Call us')
            ->assertSee('Clinic Hours')
            ->assertSee('Where we are')
            ->assertSee('Dr. Priya Verma')
            ->assertSee('Cardiologist')
            ->assertSee('Send a message')
            ->assertSee('We will reply soon.')
            ->assertSee('Submit message')
            ->assertDontSee('Have You Any Queries?')
            ->assertDontSee('Get In Touch!');
    }

    public function test_contact_details_still_come_from_site_settings_and_the_consent_from_appointment(): void
    {
        Setting::put('site.phone', '+91 55555 44444');
        Setting::put('site.address', '5 Lake Road, Jaipur');
        Setting::put('appt.consent', 'I allow the clinic to call me.');

        $this->get(route('contact'))
            ->assertOk()
            ->assertSee('+91 55555 44444')
            ->assertSee('5 Lake Road, Jaipur')
            ->assertSee('I allow the clinic to call me.');
    }

    public function test_the_form_block_and_the_map_can_be_hidden(): void
    {
        $component = Livewire::actingAs($this->superAdmin(), 'admin')->test('pages::admin.contact-page.index');

        $component->set('form_visible', false)->set('map_visible', false)->call('save')->assertHasNoErrors();

        $this->get(route('contact'))
            ->assertOk()
            ->assertDontSee('Have You Any Queries?')
            ->assertDontSee('google.com/maps', false)
            ->assertSee('Get In Touch!');

        $component->set('form_visible', true)->set('map_visible', true)->call('save');
        $this->get(route('contact'))->assertOk()->assertSee('Have You Any Queries?')->assertSee('google.com/maps', false);
    }

    public function test_the_form_block_works_without_a_photo(): void
    {
        Livewire::actingAs($this->superAdmin(), 'admin')
            ->test('pages::admin.contact-page.index')
            ->call('removePhoto');

        $this->assertSame('', Setting::get('contactpage.photo'));
        $this->get(route('contact'))->assertOk()->assertSee('Have You Any Queries?')->assertDontSee('images/contact/doctor.jpg', false);
    }

    public function test_required_texts_are_validated(): void
    {
        Livewire::actingAs($this->superAdmin(), 'admin')
            ->test('pages::admin.contact-page.index')
            ->set('title', '')
            ->set('info_heading', '')
            ->set('form_heading', '')
            ->set('form_button', '')
            ->call('save')
            ->assertHasErrors(['title', 'info_heading', 'form_heading', 'form_button']);
    }

    public function test_the_photo_can_be_replaced_removed_and_restored(): void
    {
        Storage::fake('uploads');

        $component = Livewire::actingAs($this->superAdmin(), 'admin')->test('pages::admin.contact-page.index');

        $component->set('photoFile', UploadedFile::fake()->image('doctor.jpg', 580, 720))->call('save')->assertHasNoErrors();

        $path = Setting::get('contactpage.photo');
        $this->assertStringStartsWith('uploads/contact/', $path);
        Storage::disk('uploads')->assertExists(substr($path, strlen('uploads/')));
        $this->get(route('contact'))->assertOk()->assertSee($path, false);

        $component->call('removePhoto');
        Storage::disk('uploads')->assertMissing(substr($path, strlen('uploads/')));

        $component->call('restorePhoto');
        $this->assertSame('images/contact/doctor.jpg', Setting::get('contactpage.photo'));
        $this->assertFileExists(public_path('images/contact/doctor.jpg'));
    }
}
