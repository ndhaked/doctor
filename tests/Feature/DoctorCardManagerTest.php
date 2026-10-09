<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\ProfileTreatment;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class DoctorCardManagerTest extends TestCase
{
    use RefreshDatabase;

    protected function superAdmin(): Admin
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $admin = Admin::factory()->create();
        $admin->assignRole('Super Admin');

        return $admin;
    }

    protected function item(array $attributes = []): ProfileTreatment
    {
        return ProfileTreatment::create($attributes + [
            'title' => 'Diabetes',
            'image' => 'images/symptoms/Diabetes-icon.webp',
            'is_active' => true,
            'sort_order' => 1,
        ]);
    }

    public function test_admin_without_permission_cannot_open_the_doctor_card_manager(): void
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $admin = Admin::factory()->create();
        $admin->assignRole('Admin');

        $this->actingAs($admin, 'admin')->get(route('admin.doctor-card.index'))->assertForbidden();
    }

    public function test_super_admin_sees_the_settings_and_icons(): void
    {
        $this->item(['title' => 'Thyroid disorders']);

        $this->actingAs($this->superAdmin(), 'admin')
            ->get(route('admin.doctor-card.index'))
            ->assertOk()
            ->assertSee('Doctor Card')
            ->assertSee('Show this block on the home page')
            ->assertSee('Thyroid disorders');
    }

    public function test_the_block_uses_the_original_content_by_default(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('12+ Years of Experience')
            ->assertSee('Book Appointment')
            ->assertSee('+91 99999 99999')
            ->assertSee('images/director.webp', false)
            ->assertSee('images/clinic-poster.webp', false);
    }

    public function test_admin_can_edit_the_text_and_buttons(): void
    {
        Livewire::actingAs($this->superAdmin(), 'admin')
            ->test('pages::admin.doctor-card.index')
            ->set('name', 'Dr. Priya Verma')
            ->set('qualifications', "MBBS, MD\nCardiologist")
            ->set('address', 'Heart Care Clinic, MI Road, Jaipur')
            ->set('experience', '20+ Years Experience')
            ->set('button_text', 'Contact the clinic')
            ->set('button_action', 'contact')
            ->call('saveBlock')
            ->assertHasNoErrors();

        $response = $this->get(route('home'))
            ->assertOk()
            ->assertSee('Dr. Priya Verma')
            ->assertSee('Cardiologist')
            ->assertSee('Heart Care Clinic, MI Road, Jaipur')
            ->assertSee('20+ Years Experience')
            ->assertSee('Contact the clinic');

        $html = $response->getContent();
        $this->assertStringContainsString('href="'.route('contact').'"', $html);
    }

    public function test_a_custom_button_needs_a_link(): void
    {
        Livewire::actingAs($this->superAdmin(), 'admin')
            ->test('pages::admin.doctor-card.index')
            ->set('button_action', 'custom')
            ->set('button_url', '')
            ->call('saveBlock')
            ->assertHasErrors(['button_url']);
    }

    public function test_admin_can_hide_and_show_the_block(): void
    {
        $component = Livewire::actingAs($this->superAdmin(), 'admin')->test('pages::admin.doctor-card.index');

        $component->set('visible', false)->call('saveBlock')->assertHasNoErrors();
        $this->assertSame('0', Setting::get('card.visible'));
        $this->get(route('home'))->assertOk()->assertDontSee('12+ Years of Experience');

        $component->set('visible', true)->call('saveBlock');
        $this->get(route('home'))->assertOk()->assertSee('12+ Years of Experience');
    }

    public function test_clearing_optional_parts_hides_them(): void
    {
        $component = Livewire::actingAs($this->superAdmin(), 'admin')->test('pages::admin.doctor-card.index');

        // unique values, so the checks cannot match the same words elsewhere on the home page
        $component->set('button_text', 'Zebra Button')->set('experience', 'Zebra Experience')->set('address', 'Zebra Address')->call('saveBlock');
        $this->get(route('home'))->assertOk()->assertSee('Zebra Button')->assertSee('Zebra Experience')->assertSee('Zebra Address');

        $component->set('button_text', '')->set('experience', '')->set('show_address', false)->call('saveBlock')->assertHasNoErrors();
        $this->get(route('home'))
            ->assertOk()
            ->assertDontSee('Zebra Button')
            ->assertDontSee('Zebra Experience')
            ->assertDontSee('Zebra Address');
    }

    public function test_the_phone_button_uses_the_site_phone_and_can_be_switched_off(): void
    {
        $component = Livewire::actingAs($this->superAdmin(), 'admin')->test('pages::admin.doctor-card.index');

        $withButton = substr_count($this->get(route('home'))->assertOk()->getContent(), 'href="tel:+919999999999"');

        $component->set('show_phone', false)->call('saveBlock')->assertHasNoErrors();
        $withoutButton = substr_count($this->get(route('home'))->assertOk()->getContent(), 'href="tel:+919999999999"');

        $this->assertSame($withButton - 1, $withoutButton);
    }

    public function test_the_address_follows_site_settings_unless_overridden(): void
    {
        $this->get(route('home'))->assertOk()->assertSee('Clinic, 123 MG Road, Near City Hospital, Your City');

        Setting::put('site.address', '77 New Road, Jaipur');
        $this->get(route('home'))->assertOk()->assertSee('Clinic, 77 New Road, Jaipur');

        Livewire::actingAs($this->superAdmin(), 'admin')
            ->test('pages::admin.doctor-card.index')
            ->set('address', 'Custom address line')
            ->call('saveBlock');
        $this->get(route('home'))->assertOk()->assertSee('Custom address line');
    }
    public function test_the_doctor_name_cannot_be_empty(): void
    {
        Livewire::actingAs($this->superAdmin(), 'admin')
            ->test('pages::admin.doctor-card.index')
            ->set('name', '')
            ->call('saveBlock')
            ->assertHasErrors(['name']);
    }

    public function test_admin_can_replace_remove_and_restore_the_photo_and_poster(): void
    {
        Storage::fake('uploads');

        $component = Livewire::actingAs($this->superAdmin(), 'admin')->test('pages::admin.doctor-card.index');

        $component->set('photoFile', UploadedFile::fake()->image('doctor.jpg', 600, 600))
            ->set('posterFile', UploadedFile::fake()->image('poster.jpg', 500, 750))
            ->call('saveBlock')
            ->assertHasNoErrors();

        $photo = Setting::get('card.photo');
        $poster = Setting::get('card.poster');
        $this->assertStringStartsWith('uploads/doctor-card/', $photo);
        $this->assertStringStartsWith('uploads/doctor-card/', $poster);
        Storage::disk('uploads')->assertExists(substr($photo, strlen('uploads/')));
        Storage::disk('uploads')->assertExists(substr($poster, strlen('uploads/')));

        $component->call('removeImageSlot', 'poster');
        $this->assertSame('', Setting::get('card.poster'));
        Storage::disk('uploads')->assertMissing(substr($poster, strlen('uploads/')));
        $this->get(route('home'))->assertOk()->assertDontSee('alt="Clinic information" class="w-full" loading="lazy"', false);

        $component->call('restoreImageSlot', 'poster')->call('restoreImageSlot', 'photo');
        $this->assertSame('images/clinic-poster.webp', Setting::get('card.poster'));
        $this->assertSame('images/director.webp', Setting::get('card.photo'));
        $this->assertFileExists(public_path('images/clinic-poster.webp'));
        $this->assertFileExists(public_path('images/director.webp'));
    }

    public function test_only_known_picture_slots_can_be_changed(): void
    {
        Livewire::actingAs($this->superAdmin(), 'admin')
            ->test('pages::admin.doctor-card.index')
            ->call('removeImageSlot', 'name')
            ->assertNotFound();

        // nothing was changed or cleared
        $this->assertNull(Setting::get('card.name'));
    }

    public function test_admin_can_manage_the_treatment_icons(): void
    {
        Storage::fake('uploads');

        $component = Livewire::actingAs($this->superAdmin(), 'admin')->test('pages::admin.doctor-card.index');

        $component->call('create')
            ->set('title', 'Asthma')
            ->set('icon', 'lungs')
            ->set('imageFile', UploadedFile::fake()->image('asthma.png', 64, 64))
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('showModal', false);

        $new = ProfileTreatment::firstWhere('title', 'Asthma');
        $this->assertNotNull($new);
        $this->assertSame('lungs', $new->icon);
        $this->assertStringStartsWith('uploads/doctor-card/', $new->image);

        $other = $this->item(['title' => 'Fever', 'sort_order' => 1]);
        $new->update(['sort_order' => 2]);

        $component->call('move', $new->id, 'up');
        $this->assertSame(1, $new->fresh()->sort_order);
        $this->assertSame(2, $other->fresh()->sort_order);

        $component->call('edit', $new->id)->set('title', 'Asthma care')->call('save');
        $this->assertSame('Asthma care', $new->fresh()->title);

        $component->call('toggle', $new->id);
        $this->assertFalse($new->fresh()->is_active);

        $component->call('delete', $new->id);
        $this->assertNull(ProfileTreatment::find($new->id));
        Storage::disk('uploads')->assertMissing(substr($new->image, strlen('uploads/')));
    }

    public function test_an_icon_item_needs_a_title_and_a_known_icon(): void
    {
        Livewire::actingAs($this->superAdmin(), 'admin')
            ->test('pages::admin.doctor-card.index')
            ->call('create')
            ->set('icon', 'not-an-icon')
            ->call('save')
            ->assertHasErrors(['title', 'icon']);
    }

    public function test_home_page_shows_only_visible_icons_with_a_fallback_icon(): void
    {
        $this->item(['title' => 'Visible Item']);
        $this->item(['title' => 'Icon Only Item', 'image' => null, 'icon' => 'bug', 'sort_order' => 2]);
        $this->item(['title' => 'Hidden Item', 'is_active' => false, 'sort_order' => 3]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Visible Item')
            ->assertSee('Icon Only Item')
            ->assertDontSee('Hidden Item');
    }
}
