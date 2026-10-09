<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Service;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ServiceManagerTest extends TestCase
{
    use RefreshDatabase;

    protected function superAdmin(): Admin
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $admin = Admin::factory()->create();
        $admin->assignRole('Super Admin');

        return $admin;
    }

    protected function service(array $attributes = []): Service
    {
        return Service::create($attributes + [
            'title' => 'Diabetes',
            'description' => 'Blood sugar care.',
            'icon' => 'droplet',
            'is_active' => true,
            'sort_order' => 1,
        ]);
    }

    public function test_admin_without_permission_cannot_open_the_services_manager(): void
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $admin = Admin::factory()->create();
        $admin->assignRole('Admin');

        $this->actingAs($admin, 'admin')->get(route('admin.services.index'))->assertForbidden();
    }

    public function test_super_admin_sees_the_section_text_and_cards(): void
    {
        $this->service(['title' => 'Thyroid Care']);

        $this->actingAs($this->superAdmin(), 'admin')
            ->get(route('admin.services.index'))
            ->assertOk()
            ->assertSee('Thyroid Care')
            ->assertSee('Our Main Expertise Services');
    }

    public function test_admin_can_change_the_section_text(): void
    {
        Livewire::actingAs($this->superAdmin(), 'admin')
            ->test('pages::admin.services.index')
            ->set('heading', 'What We Treat')
            ->set('intro', "Line one\nLine two")
            ->call('saveText')
            ->assertHasNoErrors();

        $this->assertSame('What We Treat', Setting::get('services.heading'));

        $this->service();

        $this->get(route('home'))->assertOk()->assertSee('What We Treat')->assertSee('Line two');
    }

    public function test_the_section_heading_cannot_be_empty(): void
    {
        Livewire::actingAs($this->superAdmin(), 'admin')
            ->test('pages::admin.services.index')
            ->set('heading', '')
            ->call('saveText')
            ->assertHasErrors(['heading']);
    }

    public function test_admin_can_add_edit_reorder_hide_and_delete_services(): void
    {
        Storage::fake('uploads');

        $component = Livewire::actingAs($this->superAdmin(), 'admin')->test('pages::admin.services.index');

        $component->call('create')
            ->set('title', 'Obesity')
            ->set('description', 'Weight management.')
            ->set('icon', 'heart')
            ->set('link_url', '/faq')
            ->set('imageFile', UploadedFile::fake()->image('obesity.jpg', 660, 372))
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('showModal', false);

        $new = Service::firstWhere('title', 'Obesity');
        $this->assertNotNull($new);
        $this->assertSame('heart', $new->icon);
        $this->assertSame('/faq', $new->link_url);
        $this->assertStringStartsWith('uploads/services/', $new->image);
        Storage::disk('uploads')->assertExists(substr($new->image, strlen('uploads/')));

        $other = $this->service(['title' => 'Asthma', 'sort_order' => 1]);
        $new->update(['sort_order' => 2]);

        $component->call('move', $new->id, 'up');
        $this->assertSame(1, $new->fresh()->sort_order);
        $this->assertSame(2, $other->fresh()->sort_order);

        $component->call('edit', $new->id)->set('title', 'Weight Care')->call('save');
        $this->assertSame('Weight Care', $new->fresh()->title);

        $component->call('toggle', $new->id);
        $this->assertFalse($new->fresh()->is_active);

        $component->call('delete', $new->id);
        $this->assertNull(Service::find($new->id));
        Storage::disk('uploads')->assertMissing(substr($new->image, strlen('uploads/')));
    }

    public function test_a_service_needs_a_title_and_a_known_icon(): void
    {
        Livewire::actingAs($this->superAdmin(), 'admin')
            ->test('pages::admin.services.index')
            ->call('create')
            ->set('icon', 'not-an-icon')
            ->call('save')
            ->assertHasErrors(['title', 'icon']);
    }

    public function test_home_page_and_header_menu_use_the_visible_services_only(): void
    {
        $this->service(['title' => 'Visible Service']);
        $this->service(['title' => 'Hidden Service', 'is_active' => false, 'sort_order' => 2]);

        $response = $this->get(route('home'))->assertOk();

        $response->assertSee('Visible Service')->assertDontSee('Hidden Service');
        // the header dropdown is filled from the same list
        $this->assertGreaterThanOrEqual(2, substr_count($response->getContent(), 'Visible Service'));
    }

    public function test_home_page_hides_the_services_section_when_there_are_none(): void
    {
        $this->get(route('home'))->assertOk()->assertDontSee('id="services"', false);
    }
}
