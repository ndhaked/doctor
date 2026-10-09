<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Review;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class StripAndAssociateManagerTest extends TestCase
{
    use RefreshDatabase;

    protected function superAdmin(): Admin
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $admin = Admin::factory()->create();
        $admin->assignRole('Super Admin');

        return $admin;
    }

    /* ---------------- Info strip ---------------- */

    public function test_admin_without_permission_cannot_open_the_strip_or_associate_managers(): void
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $admin = Admin::factory()->create();
        $admin->assignRole('Admin');

        $this->actingAs($admin, 'admin')->get(route('admin.strip.index'))->assertForbidden();
        $this->actingAs($admin, 'admin')->get(route('admin.associate.index'))->assertForbidden();
    }

    public function test_the_strip_uses_the_original_wording_and_shared_values_by_default(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Doctor Info')
            ->assertSee('Google Reviews')
            ->assertSee('Contact Us Anytime')
            ->assertSee('Get Direction')
            ->assertSee('900+ Reviews')       // the review count is shared with the Reviews section
            ->assertSee('+91 99999 99999')   // phone comes from Site Settings
            ->assertDontSee('924+ Reviews');
    }

    public function test_the_strip_follows_the_shared_reviews_and_site_settings(): void
    {
        Setting::put('reviews.rating', '4.7');
        Setting::put('reviews.count_text', '2,000 Reviews');
        Setting::put('site.phone', '+91 12345 67890');
        Setting::put('site.doctor_name', 'Dr. Priya Verma');

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('4.7')
            ->assertSee('2,000 Reviews')
            ->assertSee('+91 12345 67890')
            ->assertSee('Dr. Priya Verma is a Highly Experienced Physician');
    }

    public function test_admin_can_edit_the_strip_texts_and_links(): void
    {
        Livewire::actingAs($this->superAdmin(), 'admin')
            ->test('pages::admin.strip.index')
            ->set('doctor_name', 'Meet the doctor')
            ->set('doctor_text', 'A caring physician.')
            ->set('doctor_button_text', 'Know more')
            ->set('doctor_button_url', '/about-doctor')
            ->set('reviews_title', 'Patient ratings')
            ->set('contact_title', 'Reach us')
            ->set('contact_clinic_label', 'Visit')
            ->set('contact_link_text', 'Find us')
            ->set('contact_link_url', '/contact-us')
            ->set('book_title', 'Quick visit')
            ->set('book_button_text', 'Request a slot')
            ->call('save')
            ->assertHasNoErrors();

        $response = $this->get(route('home'))
            ->assertOk()
            ->assertSee('Meet the doctor')
            ->assertSee('A caring physician.')
            ->assertSee('Know more')
            ->assertSee('Patient ratings')
            ->assertSee('Reach us')
            ->assertSee('Find us')
            ->assertSee('Quick visit')
            ->assertSee('Request a slot')
            ->assertDontSee('Get Direction');

        $this->assertStringContainsString('href="/about-doctor"', $response->getContent());
    }

    public function test_optional_strip_parts_can_be_hidden_and_the_whole_strip_switched_off(): void
    {
        $component = Livewire::actingAs($this->superAdmin(), 'admin')->test('pages::admin.strip.index');

        $component->set('doctor_button_text', '')->set('contact_link_text', '')->set('book_button_text', '')->call('save')->assertHasNoErrors();
        $this->get(route('home'))->assertOk()->assertDontSee('Doctor Info')->assertDontSee('Get Direction');

        $component->set('visible', false)->call('save');
        $this->assertSame('0', Setting::get('strip.visible'));
        $this->get(route('home'))->assertOk()->assertDontSee('Contact Us Anytime');
    }

    public function test_the_strip_doctor_name_cannot_be_empty(): void
    {
        Livewire::actingAs($this->superAdmin(), 'admin')
            ->test('pages::admin.strip.index')
            ->set('doctor_name', '')
            ->call('save')
            ->assertHasErrors(['doctor_name']);
    }

    /* ---------------- Associate band ---------------- */

    public function test_the_associate_band_uses_the_original_content_by_default(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Associate With')
            ->assertSee('City Multi-Specialty Hospital')
            ->assertSee('View Profile')
            ->assertSee('images/hospital.webp', false)
            ->assertSee('images/associate.png', false);
    }

    public function test_admin_can_edit_the_associate_band(): void
    {
        Livewire::actingAs($this->superAdmin(), 'admin')
            ->test('pages::admin.associate.index')
            ->set('name', 'Dr. Priya Verma')
            ->set('qualifications', "MBBS, MD\nCardiologist")
            ->set('tagline', 'Heart Specialist')
            ->set('button_text', 'See profile')
            ->set('button_url', '/about-doctor')
            ->set('badge_text', 'Works At')
            ->set('hospital_name', 'Lifeline Hospital')
            ->set('hospital_role', 'Senior Consultant')
            ->set('hospital_text', 'Practising at Lifeline Hospital for ten years.')
            ->set('hospital_link', 'https://maps.example.com/lifeline')
            ->call('save')
            ->assertHasNoErrors();

        $response = $this->get(route('home'))
            ->assertOk()
            ->assertSee('Dr. Priya Verma')
            ->assertSee('Cardiologist')
            ->assertSee('Heart Specialist')
            ->assertSee('See profile')
            ->assertSee('Works At')
            ->assertSee('Lifeline Hospital')
            ->assertSee('Senior Consultant')
            ->assertSee('Practising at Lifeline Hospital for ten years.')
            ->assertDontSee('City Multi-Specialty Hospital');

        $this->assertStringContainsString('href="https://maps.example.com/lifeline"', $response->getContent());
    }

    public function test_the_associate_band_can_be_hidden_and_needs_a_name(): void
    {
        $component = Livewire::actingAs($this->superAdmin(), 'admin')->test('pages::admin.associate.index');

        $component->set('name', '')->call('save')->assertHasErrors(['name']);

        $component->set('name', 'Dr. Aditya Sharma')->set('visible', false)->call('save')->assertHasNoErrors();
        $this->get(route('home'))->assertOk()->assertDontSee('Associate With');

        $component->set('visible', true)->call('save');
        $this->get(route('home'))->assertOk()->assertSee('Associate With');
    }

    public function test_associate_pictures_can_be_replaced_removed_and_restored(): void
    {
        Storage::fake('uploads');

        $component = Livewire::actingAs($this->superAdmin(), 'admin')->test('pages::admin.associate.index');

        $component->set('hospital_imageFile', UploadedFile::fake()->image('hospital.jpg', 600, 400))
            ->set('photoFile', UploadedFile::fake()->image('doctor.jpg', 400, 400))
            ->call('save')
            ->assertHasNoErrors();

        $hospital = Setting::get('assoc.hospital_image');
        $photo = Setting::get('assoc.photo');
        $this->assertStringStartsWith('uploads/associate/', $hospital);
        $this->assertStringStartsWith('uploads/associate/', $photo);
        Storage::disk('uploads')->assertExists(substr($hospital, strlen('uploads/')));

        $component->call('removePicture', 'hospital_image');
        $this->assertSame('', Setting::get('assoc.hospital_image'));
        Storage::disk('uploads')->assertMissing(substr($hospital, strlen('uploads/')));

        $component->call('restorePicture', 'hospital_image')->call('restorePicture', 'photo');
        $this->assertSame('images/hospital.webp', Setting::get('assoc.hospital_image'));
        $this->assertSame('images/director.webp', Setting::get('assoc.photo'));
        $this->assertFileExists(public_path('images/hospital.webp'));
    }

    public function test_only_known_associate_pictures_can_be_changed(): void
    {
        Livewire::actingAs($this->superAdmin(), 'admin')
            ->test('pages::admin.associate.index')
            ->call('removePicture', 'name')
            ->assertNotFound();
    }

    public function test_doctor_name_and_qualifications_default_to_the_shared_site_values(): void
    {
        Setting::put('site.doctor_name', 'Dr. Common Name');
        Setting::put('site.doctor_qualifications', "MBBS Shared\nShared Specialist");

        // blocks that were never saved follow the shared doctor identity
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Dr. Common Name')
            ->assertSee('MBBS Shared')
            ->assertSee('Shared Specialist');

        $this->assertGreaterThanOrEqual(4, substr_count($this->get(route('home'))->getContent(), 'Dr. Common Name'));
    }
}
