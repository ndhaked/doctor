<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\BookingSlide;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class AppointmentManagerTest extends TestCase
{
    use RefreshDatabase;

    protected function superAdmin(): Admin
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $admin = Admin::factory()->create();
        $admin->assignRole('Super Admin');

        return $admin;
    }

    protected function slide(array $attributes = []): BookingSlide
    {
        return BookingSlide::create($attributes + [
            'image' => 'images/clinic-poster.webp',
            'alt' => 'Poster',
            'is_active' => true,
            'sort_order' => 1,
        ]);
    }

    public function test_admin_without_permission_cannot_open_the_appointment_manager(): void
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $admin = Admin::factory()->create();
        $admin->assignRole('Admin');

        $this->actingAs($admin, 'admin')->get(route('admin.appointment.index'))->assertForbidden();
    }

    public function test_super_admin_sees_the_texts_and_pictures(): void
    {
        $this->slide(['alt' => 'Main clinic poster']);

        $this->actingAs($this->superAdmin(), 'admin')
            ->get(route('admin.appointment.index'))
            ->assertOk()
            ->assertSee('Appointment')
            ->assertSee('Show this section on the website')
            ->assertSee('Make an Appointment')
            ->assertSee('Schedule an appointment')
            ->assertSee('{phone}');
    }

    public function test_pages_use_the_original_wording_by_default(): void
    {
        $this->slide();

        foreach (['home', 'about', 'faq'] as $route) {
            $this->get(route($route))
                ->assertOk()
                ->assertSee('Book your appointment with Dr. Aditya Sharma Physician in Jaipur')
                ->assertSee('Make an Appointment')
                ->assertSee('Schedule an appointment');
        }
    }

    public function test_admin_can_edit_the_section_and_popup_texts_for_every_page(): void
    {
        $this->slide();

        Livewire::actingAs($this->superAdmin(), 'admin')
            ->test('pages::admin.appointment.index')
            ->set('heading', 'Reserve your visit today')
            ->set('intro', 'Pick a slot that suits you.')
            ->set('clinic_name', 'Sunrise Clinic')
            ->set('form_title', 'Reserve a Slot')
            ->set('submit_text', 'Reserve now')
            ->set('consent', 'I agree to be contacted by the clinic.')
            ->set('popup_title', 'Quick Booking')
            ->set('popup_subtitle', 'We reply within an hour')
            ->set('popup_submit', 'Request call')
            ->set('popup_side_title', 'Plan your visit')
            ->set('popup_side_text', 'Call {phone} or write to {email}.')
            ->call('saveTexts')
            ->assertHasNoErrors();

        foreach (['home', 'about', 'faq', 'contact'] as $route) {
            $this->get(route($route))
                ->assertOk()
                ->assertSee('Quick Booking')
                ->assertSee('We reply within an hour')
                ->assertSee('Request call')
                ->assertSee('Plan your visit')
                ->assertSee('Call +91 99999 99999 or write to care@doctorweb.test.')
                ->assertSee('I agree to be contacted by the clinic.');
        }

        foreach (['home', 'about', 'faq'] as $route) {
            $this->get(route($route))
                ->assertSee('Reserve your visit today')
                ->assertSee('Pick a slot that suits you.')
                ->assertSee('Sunrise Clinic')
                ->assertSee('Reserve a Slot')
                ->assertSee('Reserve now');
        }
    }

    public function test_the_section_can_be_hidden_but_the_popup_keeps_working(): void
    {
        $component = Livewire::actingAs($this->superAdmin(), 'admin')->test('pages::admin.appointment.index');

        $component->set('visible', false)->call('saveTexts')->assertHasNoErrors();
        $this->assertSame('0', Setting::get('appt.visible'));

        foreach (['home', 'about', 'faq'] as $route) {
            $this->get(route($route))
                ->assertOk()
                ->assertDontSee('Book your appointment with Dr. Aditya Sharma Physician in Jaipur')
                ->assertDontSee('id="appointment"', false)
                ->assertSee('id="appointment-modal"', false);
        }

        $component->set('visible', true)->call('saveTexts');
        $this->get(route('home'))->assertOk()->assertSee('id="appointment"', false);
    }

    public function test_required_texts_are_validated(): void
    {
        Livewire::actingAs($this->superAdmin(), 'admin')
            ->test('pages::admin.appointment.index')
            ->set('heading', '')
            ->set('form_title', '')
            ->set('submit_text', '')
            ->set('popup_title', '')
            ->set('popup_submit', '')
            ->call('saveTexts')
            ->assertHasErrors(['heading', 'form_title', 'submit_text', 'popup_title', 'popup_submit']);
    }

    public function test_the_popup_logo_can_be_replaced_removed_and_restored(): void
    {
        Storage::fake('uploads');

        $component = Livewire::actingAs($this->superAdmin(), 'admin')->test('pages::admin.appointment.index');

        $component->set('popupLogoFile', UploadedFile::fake()->image('logo.png', 400, 150))->call('saveTexts')->assertHasNoErrors();

        $path = Setting::get('appt.popup_logo');
        $this->assertStringStartsWith('uploads/appointment/', $path);
        Storage::disk('uploads')->assertExists(substr($path, strlen('uploads/')));
        $this->get(route('home'))->assertOk()->assertSee($path, false);

        $component->call('removePopupLogo');
        $this->assertSame('', Setting::get('appt.popup_logo'));
        Storage::disk('uploads')->assertMissing(substr($path, strlen('uploads/')));

        $component->call('restorePopupLogo');
        $this->assertSame('', Setting::get('appt.popup_logo')); // empty = the popup uses the shared site logo
    }

    public function test_admin_can_add_reorder_hide_and_delete_pictures(): void
    {
        Storage::fake('uploads');

        $first = $this->slide(['alt' => 'First picture', 'sort_order' => 1]);
        $component = Livewire::actingAs($this->superAdmin(), 'admin')->test('pages::admin.appointment.index');

        $component->set('slideFile', UploadedFile::fake()->image('new.jpg', 500, 750))
            ->set('slideAlt', 'New picture')
            ->call('addSlide')
            ->assertHasNoErrors();

        $new = BookingSlide::firstWhere('alt', 'New picture');
        $this->assertNotNull($new);
        $this->assertStringStartsWith('uploads/appointment/', $new->image);
        $this->assertSame(2, $new->sort_order);

        $component->call('moveSlide', $new->id, 'up');
        $this->assertSame(1, $new->fresh()->sort_order);
        $this->assertSame(2, $first->fresh()->sort_order);

        $component->call('toggleSlide', $first->id);
        $this->assertFalse($first->fresh()->is_active);
        $html = $this->get(route('home'))->assertOk()->getContent();
        $this->assertStringContainsString('alt="New picture"', $html);
        $this->assertStringNotContainsString('alt="First picture"', $html);

        $component->call('deleteSlide', $new->id);
        $this->assertNull(BookingSlide::find($new->id));
        Storage::disk('uploads')->assertMissing(substr($new->image, strlen('uploads/')));

        $component->call('deleteSlide', $first->id);
        $this->assertFileExists(public_path('images/clinic-poster.webp')); // bundled picture untouched
    }

    public function test_a_picture_is_required_to_add_a_slide(): void
    {
        Livewire::actingAs($this->superAdmin(), 'admin')
            ->test('pages::admin.appointment.index')
            ->call('addSlide')
            ->assertHasErrors(['slideFile']);
    }

    public function test_the_slider_is_left_out_when_there_are_no_pictures(): void
    {
        $this->get(route('home'))->assertOk()->assertDontSee('data-poster-track', false);
    }
}
