<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Setting;
use App\Models\WhyChooseItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class WhyChooseManagerTest extends TestCase
{
    use RefreshDatabase;

    protected function superAdmin(): Admin
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $admin = Admin::factory()->create();
        $admin->assignRole('Super Admin');

        return $admin;
    }

    protected function item(array $attributes = []): WhyChooseItem
    {
        return WhyChooseItem::create($attributes + [
            'title' => 'Expertise',
            'content' => 'Years of experience.',
            'is_active' => true,
            'sort_order' => 1,
        ]);
    }

    public function test_admin_without_permission_cannot_open_the_why_choose_manager(): void
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $admin = Admin::factory()->create();
        $admin->assignRole('Admin');

        $this->actingAs($admin, 'admin')->get(route('admin.why-choose.index'))->assertForbidden();
    }

    public function test_super_admin_sees_the_block_settings_and_rows(): void
    {
        $this->item(['title' => 'Trusted care']);

        $this->actingAs($this->superAdmin(), 'admin')
            ->get(route('admin.why-choose.index'))
            ->assertOk()
            ->assertSee('Why Choose')
            ->assertSee('Show this block on the home page')
            ->assertSee('Trusted care');
    }

    public function test_the_block_is_shown_on_the_home_page_by_default(): void
    {
        $this->item(['title' => 'Expertise']);

        $this->get(route('home'))->assertOk()->assertSee('Why Choose?')->assertSee('Expertise');
    }

    public function test_admin_can_edit_the_block_text(): void
    {
        Livewire::actingAs($this->superAdmin(), 'admin')
            ->test('pages::admin.why-choose.index')
            ->set('heading', 'Our strengths')
            ->set('subheading', 'What sets us apart')
            ->set('content', 'We put patients first.')
            ->call('saveBlock')
            ->assertHasNoErrors();

        $this->assertSame('Our strengths', Setting::get('whychoose.heading'));

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Our strengths')
            ->assertSee('What sets us apart')
            ->assertSee('We put patients first.');
    }

    public function test_admin_can_hide_and_show_the_block(): void
    {
        $this->item();

        $component = Livewire::actingAs($this->superAdmin(), 'admin')->test('pages::admin.why-choose.index');

        $component->set('visible', false)->call('saveBlock')->assertHasNoErrors();
        $this->assertSame('0', Setting::get('whychoose.visible'));
        $this->get(route('home'))->assertOk()->assertDontSee('Why Choose?');

        $component->set('visible', true)->call('saveBlock');
        $this->get(route('home'))->assertOk()->assertSee('Why Choose?');
    }

    public function test_the_heading_cannot_be_empty(): void
    {
        Livewire::actingAs($this->superAdmin(), 'admin')
            ->test('pages::admin.why-choose.index')
            ->set('heading', '')
            ->call('saveBlock')
            ->assertHasErrors(['heading']);
    }

    public function test_admin_can_manage_the_rows(): void
    {
        $component = Livewire::actingAs($this->superAdmin(), 'admin')->test('pages::admin.why-choose.index');

        $component->call('create')
            ->set('title', 'Reputation')
            ->set('body', 'Thousands of patients trust us.')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('showModal', false);

        $new = WhyChooseItem::firstWhere('title', 'Reputation');
        $this->assertNotNull($new);

        $other = $this->item(['title' => 'Expertise', 'sort_order' => 1]);
        $new->update(['sort_order' => 2]);

        $component->call('move', $new->id, 'up');
        $this->assertSame(1, $new->fresh()->sort_order);
        $this->assertSame(2, $other->fresh()->sort_order);

        $component->call('edit', $new->id)->set('title', 'Great reputation')->call('save');
        $this->assertSame('Great reputation', $new->fresh()->title);

        $component->call('toggle', $new->id);
        $this->assertFalse($new->fresh()->is_active);

        $component->call('delete', $new->id);
        $this->assertNull(WhyChooseItem::find($new->id));
    }

    public function test_a_row_needs_a_title(): void
    {
        Livewire::actingAs($this->superAdmin(), 'admin')
            ->test('pages::admin.why-choose.index')
            ->call('create')
            ->call('save')
            ->assertHasErrors(['title']);
    }

    public function test_only_visible_rows_appear_and_the_first_one_starts_open(): void
    {
        $this->item(['title' => 'First Row', 'sort_order' => 1]);
        $this->item(['title' => 'Second Row', 'sort_order' => 2]);
        $this->item(['title' => 'Hidden Row', 'sort_order' => 3, 'is_active' => false]);

        $html = $this->get(route('home'))->assertOk()->assertSee('First Row')->assertSee('Second Row')->assertDontSee('Hidden Row')->getContent();

        $this->assertSame(2, substr_count($html, '<div data-acc-item'));
        $this->assertSame(1, substr_count($html, 'data-open="true"'));
    }

    public function test_the_text_still_shows_when_there_are_no_rows(): void
    {
        $this->get(route('home'))->assertOk()->assertSee('Why Choose?')->assertDontSee('data-acc-item', false);
    }
}
