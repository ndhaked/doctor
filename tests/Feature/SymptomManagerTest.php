<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Setting;
use App\Models\Symptom;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class SymptomManagerTest extends TestCase
{
    use RefreshDatabase;

    protected function superAdmin(): Admin
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $admin = Admin::factory()->create();
        $admin->assignRole('Super Admin');

        return $admin;
    }

    protected function symptom(array $attributes = []): Symptom
    {
        return Symptom::create($attributes + [
            'title' => 'Fever',
            'description' => 'A high temperature.',
            'icon' => 'images/symptoms/sick.webp',
            'is_active' => true,
            'sort_order' => 1,
        ]);
    }

    public function test_admin_without_permission_cannot_open_the_symptoms_manager(): void
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $admin = Admin::factory()->create();
        $admin->assignRole('Admin');

        $this->actingAs($admin, 'admin')->get(route('admin.symptoms.index'))->assertForbidden();
    }

    public function test_super_admin_sees_the_section_text_and_cards(): void
    {
        $this->symptom(['title' => 'Chest Pain']);

        $this->actingAs($this->superAdmin(), 'admin')
            ->get(route('admin.symptoms.index'))
            ->assertOk()
            ->assertSee('Symptoms Section')
            ->assertSee('Chest Pain')
            ->assertSee(Symptom::SECTION_DEFAULTS['symptoms.heading']);
    }

    public function test_admin_can_change_the_section_text(): void
    {
        Livewire::actingAs($this->superAdmin(), 'admin')
            ->test('pages::admin.symptoms.index')
            ->set('eyebrow', 'Warning signs')
            ->set('heading', 'When to see a doctor')
            ->set('intro', 'Look out for these signs.')
            ->call('saveText')
            ->assertHasNoErrors();

        $this->assertSame('When to see a doctor', Setting::get('symptoms.heading'));

        $this->symptom();

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('When to see a doctor')
            ->assertSee('Warning signs')
            ->assertSee('Look out for these signs.');
    }

    public function test_the_section_heading_cannot_be_empty(): void
    {
        Livewire::actingAs($this->superAdmin(), 'admin')
            ->test('pages::admin.symptoms.index')
            ->set('heading', '')
            ->call('saveText')
            ->assertHasErrors(['heading']);
    }

    public function test_admin_can_add_edit_reorder_hide_and_delete_cards(): void
    {
        Storage::fake('uploads');

        $component = Livewire::actingAs($this->superAdmin(), 'admin')->test('pages::admin.symptoms.index');

        $component->call('create')
            ->set('title', 'Dizziness')
            ->set('description', 'Feeling faint or unsteady.')
            ->set('iconFile', UploadedFile::fake()->image('icon.png', 64, 64))
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('showModal', false);

        $new = Symptom::firstWhere('title', 'Dizziness');
        $this->assertNotNull($new);
        $this->assertStringStartsWith('uploads/symptoms/', $new->icon);
        Storage::disk('uploads')->assertExists(substr($new->icon, strlen('uploads/')));

        $other = $this->symptom(['title' => 'Cough', 'sort_order' => 1]);
        $new->update(['sort_order' => 2]);

        $component->call('move', $new->id, 'up');
        $this->assertSame(1, $new->fresh()->sort_order);
        $this->assertSame(2, $other->fresh()->sort_order);

        $component->call('edit', $new->id)->set('title', 'Vertigo')->call('save');
        $this->assertSame('Vertigo', $new->fresh()->title);

        $component->call('toggle', $new->id);
        $this->assertFalse($new->fresh()->is_active);

        $component->call('delete', $new->id);
        $this->assertNull(Symptom::find($new->id));
        Storage::disk('uploads')->assertMissing(substr($new->icon, strlen('uploads/')));
    }

    public function test_a_card_needs_a_title(): void
    {
        Livewire::actingAs($this->superAdmin(), 'admin')
            ->test('pages::admin.symptoms.index')
            ->call('create')
            ->call('save')
            ->assertHasErrors(['title']);
    }

    public function test_home_page_shows_only_visible_cards_and_hides_an_empty_section(): void
    {
        $this->get(route('home'))->assertOk()->assertDontSee('id="symptoms"', false);

        $this->symptom(['title' => 'Visible Symptom']);
        $this->symptom(['title' => 'Hidden Symptom', 'is_active' => false, 'sort_order' => 2]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('id="symptoms"', false)
            ->assertSee('Visible Symptom')
            ->assertDontSee('Hidden Symptom');
    }
}
