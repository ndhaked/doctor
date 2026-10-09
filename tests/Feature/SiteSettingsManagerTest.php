<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SiteSettingsManagerTest extends TestCase
{
    use RefreshDatabase;

    protected function superAdmin(): Admin
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $admin = Admin::factory()->create();
        $admin->assignRole('Super Admin');

        return $admin;
    }

    public function test_admin_without_permission_cannot_open_site_settings(): void
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $admin = Admin::factory()->create();
        $admin->assignRole('Admin');

        $this->actingAs($admin, 'admin')->get(route('admin.site.index'))->assertForbidden();
    }

    public function test_super_admin_sees_the_current_values(): void
    {
        $this->actingAs($this->superAdmin(), 'admin')
            ->get(route('admin.site.index'))
            ->assertOk()
            ->assertSee('Site Settings')
            ->assertSee('care@doctorweb.test')
            ->assertSee('Physician in Jaipur|#');
    }

    public function test_every_page_uses_the_same_default_contact_details(): void
    {
        foreach (['home', 'about', 'contact', 'faq', 'health-tips'] as $route) {
            $this->get(route($route))
                ->assertOk()
                ->assertSee('+91 99999 99999')
                ->assertSee('care@doctorweb.test')
                ->assertDontSee('98765 43210');
        }
    }

    public function test_changing_the_contact_details_updates_every_page(): void
    {
        Livewire::actingAs($this->superAdmin(), 'admin')
            ->test('pages::admin.site.index')
            ->set('phone', '+91 77777 66666')
            ->set('email', 'hello@clinic.test')
            ->set('address', '9 Station Road, Jaipur')
            ->set('timing_title', 'Morning OPD')
            ->set('timing_days', 'Mon to Fri')
            ->set('timing_hours', '9 AM to 1 PM')
            ->set('timing_note', 'Walk-ins welcome')
            ->call('save')
            ->assertHasNoErrors();

        foreach (['home', 'about', 'contact', 'faq'] as $route) {
            $response = $this->get(route($route))
                ->assertOk()
                ->assertSee('+91 77777 66666')
                ->assertSee('hello@clinic.test')
                ->assertDontSee('+91 99999 99999')
                ->assertDontSee('care@doctorweb.test');

            $this->assertStringContainsString('href="tel:+917777766666"', $response->getContent());
        }

        $this->get(route('contact'))->assertSee('9 Station Road, Jaipur')->assertSee('Morning OPD')->assertSee('Mon to Fri')->assertSee('9 AM to 1 PM')->assertSee('Walk-ins welcome');
        $this->get(route('home'))->assertSee('Morning OPD')->assertSee('Walk-ins welcome')->assertDontSee('Consultation with Prior Appointment Only');
    }

    public function test_social_links_are_real_links_and_empty_ones_are_hidden(): void
    {
        Livewire::actingAs($this->superAdmin(), 'admin')
            ->test('pages::admin.site.index')
            ->set('facebook', 'https://facebook.com/ourclinic')
            ->set('instagram', '')
            ->set('youtube', 'https://youtube.com/@ourclinic')
            ->call('save')
            ->assertHasNoErrors();

        $html = $this->get(route('home'))->assertOk()->getContent();

        $this->assertStringContainsString('href="https://facebook.com/ourclinic"', $html);
        $this->assertStringContainsString('href="https://youtube.com/@ourclinic"', $html);
        $this->assertStringNotContainsString('aria-label="Instagram"', $html);
        $this->assertStringNotContainsString('title="Instagram"', $html);
    }

    public function test_footer_text_links_and_credit_are_editable(): void
    {
        Livewire::actingAs($this->superAdmin(), 'admin')
            ->test('pages::admin.site.index')
            ->set('footer_text', 'Trusted care for every family.')
            ->set('footer_links_text', "Heart Clinic|/about-doctor\nContact|/contact-us")
            ->set('copyright_name', 'Dr. Priya Verma')
            ->set('credit_name', 'Web Studio')
            ->call('save')
            ->assertHasNoErrors();

        $response = $this->get(route('faq'))
            ->assertOk()
            ->assertSee('Trusted care for every family.')
            ->assertSee('Heart Clinic')
            ->assertSee('Dr. Priya Verma')
            ->assertSee('Web Studio')
            ->assertDontSee('Nirbhay Dhaked')
            ->assertDontSee('Asthma Specialist in Jaipur');

        $this->assertStringContainsString('href="/about-doctor"', $response->getContent());
    }

    public function test_the_footer_links_box_hides_when_empty(): void
    {
        Livewire::actingAs($this->superAdmin(), 'admin')
            ->test('pages::admin.site.index')
            ->set('footer_links_text', '')
            ->call('save');

        $this->get(route('faq'))->assertOk()->assertDontSee('data-footer-links', false);
    }

    public function test_the_contact_page_map_uses_the_saved_link(): void
    {
        Livewire::actingAs($this->superAdmin(), 'admin')
            ->test('pages::admin.site.index')
            ->set('map_url', 'https://www.google.com/maps/embed?pb=TEST123')
            ->call('save');

        $this->get(route('contact'))->assertOk()->assertSee('https://www.google.com/maps/embed?pb=TEST123', false);
    }

    public function test_the_email_must_be_valid(): void
    {
        Livewire::actingAs($this->superAdmin(), 'admin')
            ->test('pages::admin.site.index')
            ->set('email', 'not-an-email')
            ->call('save')
            ->assertHasErrors(['email']);

        $this->assertNull(Setting::get('site.email'));
    }

    public function test_bar_colours_are_saved_and_used_on_the_website(): void
    {
        Livewire::actingAs($this->superAdmin(), 'admin')
            ->test('pages::admin.site.index')
            ->set('topbar_bg', '#112233')->set('topbar_text', '#ffeeaa')->set('header_bg', '#f0f0f0')
            ->call('save')->assertHasNoErrors();

        $this->get(route('faq'))->assertOk()
            ->assertSee('background-color: #112233; color: #ffeeaa', false)
            ->assertSee('background-color: #f0f0f0', false);
    }

    public function test_colours_must_be_hex_values(): void
    {
        Livewire::actingAs($this->superAdmin(), 'admin')
            ->test('pages::admin.site.index')
            ->set('topbar_bg', 'red; background:url(x)')
            ->call('save')->assertHasErrors(['topbar_bg']);
    }

    public function test_logo_can_be_uploaded_shown_in_header_and_footer_and_removed(): void
    {
        $component = Livewire::actingAs($this->superAdmin(), 'admin')
            ->test('pages::admin.site.index')
            ->set('logo_upload', \Illuminate\Http\UploadedFile::fake()->image('logo.png', 300, 100))
            ->call('save')->assertHasNoErrors();

        $path = Setting::get('site.logo');
        $this->assertStringStartsWith('uploads/logo/', $path);
        $this->assertFileExists(public_path($path));

        $html = $this->get(route('faq'))->assertOk()->getContent();
        $this->assertSame(3, substr_count($html, asset($path)), 'logo should appear in header, footer and popup');

        $component->set('remove_logo', true)->call('save');
        $this->assertSame('', Setting::get('site.logo'));
        $this->assertFileDoesNotExist(public_path($path));
        $this->get(route('faq'))->assertDontSee($path, false);
    }

    public function test_non_image_logo_is_rejected(): void
    {
        Livewire::actingAs($this->superAdmin(), 'admin')
            ->test('pages::admin.site.index')
            ->set('logo_upload', \Illuminate\Http\UploadedFile::fake()->create('x.pdf', 10, 'application/pdf'))
            ->call('save')->assertHasErrors(['logo_upload']);
    }

    public function test_brand_name_and_tagline_are_used_in_header_footer_titles_and_popup(): void
    {
        Livewire::actingAs($this->superAdmin(), 'admin')
            ->test('pages::admin.site.index')
            ->set('brand_name', 'Sunrise Heart Care')->set('brand_tagline', 'Hospital')->set('meta_title', 'Cardiology Experts')
            ->call('save')->assertHasNoErrors();

        $html = $this->get(route('faq'))->assertOk()->getContent();
        $this->assertStringContainsString('<title>FAQs - Sunrise Heart Care Hospital</title>', $html);
        $this->assertGreaterThanOrEqual(3, substr_count($html, 'Sunrise Heart Care'), 'header, footer and popup');
        $this->assertStringNotContainsString('DoctorWeb', $html);
        $this->assertStringNotContainsString('images/logo.webp', $html);
    }

    public function test_popup_uses_the_site_logo_unless_it_has_its_own(): void
    {
        \App\Models\Setting::put('site.logo', 'uploads/logo/site.png');
        $this->get(route('faq'))->assertSee('uploads/logo/site.png', false);

        \App\Models\Setting::put('appt.popup_logo', 'uploads/appointment/popup.png');
        $html = $this->get(route('faq'))->getContent();
        $this->assertStringContainsString('uploads/appointment/popup.png', $html);
    }
}
