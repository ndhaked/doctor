<?php

namespace Tests\Feature;

use App\Models\AboutExpertise;
use App\Models\AboutTreatment;
use App\Models\Admin;
use App\Models\Setting;
use App\Support\RichText;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class AboutPageManagerTest extends TestCase
{
    use RefreshDatabase;

    protected function superAdmin(): Admin
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $admin = Admin::factory()->create();
        $admin->assignRole('Super Admin');

        return $admin;
    }

    public function test_admin_without_permission_cannot_open_the_about_page_manager(): void
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $admin = Admin::factory()->create();
        $admin->assignRole('Admin');

        $this->actingAs($admin, 'admin')->get(route('admin.about-page.index'))->assertForbidden();
    }

    public function test_super_admin_sees_the_current_content(): void
    {
        $this->actingAs($this->superAdmin(), 'admin')
            ->get(route('admin.about-page.index'))
            ->assertOk()
            ->assertSee('About Page')
            ->assertSee('Overview')
            ->assertSee('Education &amp; Qualification', false)
            ->assertSee('Expertise cards');
    }

    public function test_the_page_uses_the_original_content_by_default(): void
    {
        $this->seed(\Database\Seeders\AboutPageSeeder::class);

        $html = $this->get(route('about'))
            ->assertOk()
            ->assertSee('About Us')
            ->assertSee('Overview – Dr. Aditya Sharma', false)
            ->assertSee('(Consultant Physician and Diabetologist)')
            ->assertSee('Expertise Services:')
            ->assertSee('Looking for a Physician and diabetologist in Jaipur')
            ->assertSee('Our Main Treatments')
            ->assertSee('Obesity')
            ->getContent();

        $this->assertSame(7, substr_count($html, 'data-tab="'));
        // the rich-text link in the overview paragraph still works
        $this->assertStringContainsString('<a href="/" class="font-bold text-emerald-950 underline">Physician in Jaipur</a>', $html);
    }

    public function test_admin_can_edit_the_banner_overview_and_bands(): void
    {
        Livewire::actingAs($this->superAdmin(), 'admin')
            ->test('pages::admin.about-page.index')
            ->set('banner_label', 'Meet us')
            ->set('banner_name', 'Dr. Priya Verma')
            ->set('banner_qualifications', "MBBS, MD\nCardiologist")
            ->set('banner_text', 'Call {phone} to book.')
            ->set('banner_button_text', 'Get in touch')
            ->set('banner_button_url', '/faq')
            ->set('overview_title', 'About Dr. Priya')
            ->set('overview_subtitle', '(Heart specialist)')
            ->set('overview_text', "First para with **bold** words.\n\nSecond para with [a link](/faq).")
            ->set('expertise_heading', 'What we treat:')
            ->set('cta_heading', 'Ready to visit?')
            ->set('cta_text', 'We are open six days a week.')
            ->set('cta_button_text', 'Reserve a visit')
            ->set('treat_eyebrow', 'Care')
            ->set('treat_heading', 'Conditions we treat')
            ->set('treat_intro', 'A few of the things we handle.')
            ->call('savePage')
            ->assertHasNoErrors();

        $this->seed(\Database\Seeders\AboutPageSeeder::class);

        $html = $this->get(route('about'))
            ->assertOk()
            ->assertSee('Meet us')
            ->assertSee('Dr. Priya Verma')
            ->assertSee('Cardiologist')
            ->assertSee('Call +91 99999 99999 to book.')
            ->assertSee('Get in touch')
            ->assertSee('About Dr. Priya')
            ->assertSee('(Heart specialist)')
            ->assertSee('What we treat:')
            ->assertSee('Ready to visit?')
            ->assertSee('We are open six days a week.')
            ->assertSee('Reserve a visit')
            ->assertSee('Conditions we treat')
            ->assertSee('A few of the things we handle.')
            ->assertDontSee('Our Main Treatments')
            ->getContent();

        $this->assertStringContainsString('<strong>bold</strong>', $html);
        $this->assertStringContainsString('href="/faq"', $html);
    }

    public function test_an_empty_tab_list_hides_that_tab(): void
    {
        $component = Livewire::actingAs($this->superAdmin(), 'admin')->test('pages::admin.about-page.index');

        $component->set('research', '')->call('savePage')->assertHasNoErrors();
        $html = $this->get(route('about'))->assertOk()->getContent();
        $this->assertStringNotContainsString('data-tab="research"', $html);
        $this->assertStringContainsString('data-tab="education"', $html);

        $component->set('awards', "A single award\nAnother award")->call('savePage');
        $this->get(route('about'))->assertOk()->assertSee('A single award')->assertSee('Another award');
    }

    public function test_the_dark_band_and_treatments_block_can_be_hidden(): void
    {
        $this->seed(\Database\Seeders\AboutPageSeeder::class);

        Livewire::actingAs($this->superAdmin(), 'admin')
            ->test('pages::admin.about-page.index')
            ->set('cta_visible', false)
            ->set('treat_visible', false)
            ->call('savePage')
            ->assertHasNoErrors();

        $this->get(route('about'))
            ->assertOk()
            ->assertDontSee('Looking for a Physician and diabetologist')
            ->assertDontSee('Our Main Treatments');
    }

    public function test_required_texts_are_validated(): void
    {
        Livewire::actingAs($this->superAdmin(), 'admin')
            ->test('pages::admin.about-page.index')
            ->set('banner_name', '')
            ->set('overview_title', '')
            ->set('cta_heading', '')
            ->set('treat_heading', '')
            ->call('savePage')
            ->assertHasErrors(['banner_name', 'overview_title', 'cta_heading', 'treat_heading']);
    }

    public function test_pictures_can_be_replaced_removed_and_restored(): void
    {
        Storage::fake('uploads');

        $component = Livewire::actingAs($this->superAdmin(), 'admin')->test('pages::admin.about-page.index');

        $component->set('banner_photoFile', UploadedFile::fake()->image('banner.jpg', 500, 500))->call('savePage')->assertHasNoErrors();

        $path = Setting::get('about.banner_photo');
        $this->assertStringStartsWith('uploads/about/', $path);
        Storage::disk('uploads')->assertExists(substr($path, strlen('uploads/')));

        $component->call('removePicture', 'banner_photo');
        $this->assertSame('', Setting::get('about.banner_photo'));
        Storage::disk('uploads')->assertMissing(substr($path, strlen('uploads/')));

        $component->call('restorePicture', 'banner_photo')->call('restorePicture', 'overview_photo');
        $this->assertSame('images/about/user-img-slider.png', Setting::get('about.banner_photo'));
        $this->assertSame('images/about/about-doctor-1.jpg', Setting::get('about.overview_photo'));
        $this->assertFileExists(public_path('images/about/user-img-slider.png'));
    }

    public function test_only_known_pictures_can_be_changed(): void
    {
        Livewire::actingAs($this->superAdmin(), 'admin')
            ->test('pages::admin.about-page.index')
            ->call('removePicture', 'banner_name')
            ->assertNotFound();
    }

    public function test_admin_can_manage_expertise_cards_and_treatment_photos(): void
    {
        Storage::fake('uploads');

        $component = Livewire::actingAs($this->superAdmin(), 'admin')->test('pages::admin.about-page.index');

        foreach (['expertise' => AboutExpertise::class, 'treatment' => AboutTreatment::class] as $kind => $model) {
            $component->call('create', $kind)
                ->set('title', 'Test '.$kind)
                ->set('imageFile', UploadedFile::fake()->image('card.png', 120, 120))
                ->call('save')
                ->assertHasNoErrors()
                ->assertSet('showModal', false);

            $new = $model::firstWhere('title', 'Test '.$kind);
            $this->assertNotNull($new);
            $this->assertStringStartsWith('uploads/about/', $new->image);

            $other = $model::create(['title' => 'Other', 'image' => 'images/about/cl-3.png', 'sort_order' => 1]);
            $new->update(['sort_order' => 2]);

            $component->call('move', $kind, $new->id, 'up');
            $this->assertSame(1, $new->fresh()->sort_order);
            $this->assertSame(2, $other->fresh()->sort_order);

            $component->call('edit', $kind, $new->id)->set('title', 'Renamed '.$kind)->call('save');
            $this->assertSame('Renamed '.$kind, $new->fresh()->title);

            $component->call('toggle', $kind, $new->id);
            $this->assertFalse($new->fresh()->is_active);

            $component->call('delete', $kind, $new->id);
            $this->assertNull($model::find($new->id));
            Storage::disk('uploads')->assertMissing(substr($new->image, strlen('uploads/')));
        }
    }

    public function test_hidden_cards_are_left_out_of_the_page(): void
    {
        AboutExpertise::create(['title' => 'Visible Expertise', 'image' => 'images/about/cl-3.png', 'sort_order' => 1]);
        AboutExpertise::create(['title' => 'Hidden Expertise', 'image' => 'images/about/cl-3.png', 'is_active' => false, 'sort_order' => 2]);
        AboutTreatment::create(['title' => 'Visible Treatment', 'image' => 'images/about/services-5.jpg', 'sort_order' => 1]);
        AboutTreatment::create(['title' => 'Hidden Treatment', 'image' => 'images/about/services-5.jpg', 'is_active' => false, 'sort_order' => 2]);

        $this->get(route('about'))
            ->assertOk()
            ->assertSee('Visible Expertise')
            ->assertSee('Visible Treatment')
            ->assertDontSee('Hidden Expertise')
            ->assertDontSee('Hidden Treatment');
    }

    public function test_a_card_needs_a_title_and_an_unknown_kind_is_rejected(): void
    {
        $component = Livewire::actingAs($this->superAdmin(), 'admin')->test('pages::admin.about-page.index');

        $component->call('create', 'expertise')->call('save')->assertHasErrors(['title']);
        $component->call('create', 'nonsense')->assertNotFound();
    }

    public function test_rich_text_is_escaped_and_only_safe_markers_are_converted(): void
    {
        $html = (string) RichText::inline("<script>alert(1)</script> **bold** [ok](/faq) [bad](javascript:alert(1))");

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringContainsString('<strong>bold</strong>', $html);
        $this->assertStringContainsString('<a href="/faq"', $html);
        $this->assertStringNotContainsString('href="javascript:', $html);
    }
}
