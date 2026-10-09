<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\FaqGroup;
use App\Models\FaqItem;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class FaqManagerTest extends TestCase
{
    use RefreshDatabase;

    protected function superAdmin(): Admin
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $admin = Admin::factory()->create();
        $admin->assignRole('Super Admin');

        return $admin;
    }

    protected function group(string $title = 'Asthma', string $placement = 'faq_page', int $order = 1): FaqGroup
    {
        return FaqGroup::create(['title' => $title, 'placement' => $placement, 'sort_order' => $order]);
    }

    public function test_admin_without_permission_cannot_open_the_faq_manager(): void
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $admin = Admin::factory()->create();
        $admin->assignRole('Admin');

        $this->actingAs($admin, 'admin')->get(route('admin.faqs.index'))->assertForbidden();
    }

    public function test_super_admin_sees_groups_and_questions(): void
    {
        $group = $this->group('Joint Pain');
        $group->items()->create(['question' => 'What causes joint pain?', 'answer' => 'Many things.', 'sort_order' => 1]);

        $this->actingAs($this->superAdmin(), 'admin')
            ->get(route('admin.faqs.index'))
            ->assertOk()
            ->assertSee('Joint Pain')
            ->assertSee('What causes joint pain?');
    }

    public function test_admin_can_add_edit_and_delete_a_group(): void
    {
        $component = Livewire::actingAs($this->superAdmin(), 'admin')->test('pages::admin.faqs.index');

        $component->call('createGroup')
            ->set('groupTitle', 'Diabetes')
            ->call('saveGroup')
            ->assertHasNoErrors()
            ->assertSet('showGroupModal', false);

        $group = FaqGroup::firstWhere('title', 'Diabetes');
        $this->assertNotNull($group);
        $this->assertSame('faq_page', $group->placement);
        $component->assertSet('selectedGroupId', $group->id);

        $component->call('editGroup', $group->id)->set('groupTitle', 'Diabetes Care')->call('saveGroup');
        $this->assertSame('Diabetes Care', $group->fresh()->title);

        $component->call('deleteGroup', $group->id);
        $this->assertNull(FaqGroup::find($group->id));
    }

    public function test_a_group_needs_a_name(): void
    {
        Livewire::actingAs($this->superAdmin(), 'admin')
            ->test('pages::admin.faqs.index')
            ->call('createGroup')
            ->call('saveGroup')
            ->assertHasErrors(['groupTitle']);
    }

    public function test_admin_can_manage_questions_in_a_group(): void
    {
        $group = $this->group();

        $component = Livewire::actingAs($this->superAdmin(), 'admin')
            ->test('pages::admin.faqs.index')
            ->call('selectGroup', $group->id);

        $component->call('createItem')
            ->set('question', 'What is asthma?')
            ->set('answer', 'A condition of the airways.')
            ->call('saveItem')
            ->assertHasNoErrors();

        $component->call('createItem')
            ->set('question', 'What are triggers?')
            ->set('answer', 'Dust, smoke and more.')
            ->call('saveItem');

        $first = FaqItem::firstWhere('question', 'What is asthma?');
        $second = FaqItem::firstWhere('question', 'What are triggers?');
        $this->assertSame([1, 2], [$first->sort_order, $second->sort_order]);

        $component->call('moveItem', $second->id, 'up');
        $this->assertSame(1, $second->fresh()->sort_order);
        $this->assertSame(2, $first->fresh()->sort_order);

        $component->call('toggleItem', $first->id);
        $this->assertFalse($first->fresh()->is_active);

        $component->call('editItem', $first->id)->set('answer', 'Updated answer')->call('saveItem');
        $this->assertSame('Updated answer', $first->fresh()->answer);

        $component->call('deleteItem', $first->id);
        $this->assertNull(FaqItem::find($first->id));
    }

    public function test_a_question_needs_text_and_an_answer(): void
    {
        $group = $this->group();

        Livewire::actingAs($this->superAdmin(), 'admin')
            ->test('pages::admin.faqs.index')
            ->call('selectGroup', $group->id)
            ->call('createItem')
            ->call('saveItem')
            ->assertHasErrors(['question', 'answer']);
    }

    public function test_faq_page_shows_only_visible_groups_and_questions(): void
    {
        $shown = $this->group('Visible Group', 'faq_page', 1);
        $shown->items()->create(['question' => 'Visible question?', 'answer' => 'Yes.', 'sort_order' => 1]);
        $shown->items()->create(['question' => 'Hidden question?', 'answer' => 'No.', 'sort_order' => 2, 'is_active' => false]);

        $hidden = $this->group('Hidden Group', 'faq_page', 2);
        $hidden->update(['is_active' => false]);
        $hidden->items()->create(['question' => 'In a hidden group?', 'answer' => 'No.', 'sort_order' => 1]);

        $this->get(route('faq'))
            ->assertOk()
            ->assertSee('Visible Group')
            ->assertSee('Visible question?')
            ->assertDontSee('Hidden question?')
            ->assertDontSee('Hidden Group')
            ->assertDontSee('In a hidden group?');
    }

    public function test_faq_page_renders_when_there_are_no_faqs(): void
    {
        $this->get(route('faq'))->assertOk()->assertSee('No FAQs have been published yet');
    }

    protected function homeFaq(): FaqGroup
    {
        $home = $this->group('Home', 'home', 1);
        $home->items()->create(['question' => 'Home question?', 'answer' => 'Home answer.', 'sort_order' => 1]);

        return $home;
    }

    public function test_home_faq_block_uses_the_original_texts_and_background_by_default(): void
    {
        $this->homeFaq();

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Frequently Asked Questions')
            ->assertSee('images/faq-bg.jpg', false);
    }

    public function test_admin_can_edit_the_home_faq_block_texts(): void
    {
        $this->homeFaq();

        Livewire::actingAs($this->superAdmin(), 'admin')
            ->test('pages::admin.faqs.index')
            ->set('home_eyebrow', 'Got questions?')
            ->set('home_heading', 'Answers for you')
            ->call('saveHomeBlock')
            ->assertHasNoErrors();

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Got questions?')
            ->assertSee('Answers for you')
            ->assertDontSee('Frequently Asked Questions');
    }

    public function test_admin_can_hide_and_show_the_home_faq_block(): void
    {
        $this->homeFaq();

        $component = Livewire::actingAs($this->superAdmin(), 'admin')->test('pages::admin.faqs.index');

        $component->set('home_visible', false)->call('saveHomeBlock')->assertHasNoErrors();
        $this->assertSame('0', Setting::get('faq.home_visible'));
        $this->get(route('home'))->assertOk()->assertDontSee('Home question?');

        $component->set('home_visible', true)->call('saveHomeBlock');
        $this->get(route('home'))->assertOk()->assertSee('Home question?');
    }

    public function test_the_home_faq_heading_cannot_be_empty(): void
    {
        Livewire::actingAs($this->superAdmin(), 'admin')
            ->test('pages::admin.faqs.index')
            ->set('home_heading', '')
            ->call('saveHomeBlock')
            ->assertHasErrors(['home_heading']);
    }

    public function test_admin_can_replace_remove_and_restore_the_background_photo(): void
    {
        Storage::fake('uploads');
        $this->homeFaq();

        $component = Livewire::actingAs($this->superAdmin(), 'admin')->test('pages::admin.faqs.index');

        $component->set('backgroundFile', UploadedFile::fake()->image('bg.jpg', 1600, 900))
            ->call('saveHomeBlock')
            ->assertHasNoErrors();

        $path = Setting::get('faq.home_background');
        $this->assertStringStartsWith('uploads/faq/', $path);
        Storage::disk('uploads')->assertExists(substr($path, strlen('uploads/')));

        $component->call('removeBackground');
        $this->assertSame('', Setting::get('faq.home_background'));
        Storage::disk('uploads')->assertMissing(substr($path, strlen('uploads/')));
        $this->get(route('home'))->assertOk()->assertDontSee('faq-bg.jpg', false);

        $component->call('restoreBackground');
        $this->assertSame('images/faq-bg.jpg', Setting::get('faq.home_background'));
        $this->assertFileExists(public_path('images/faq-bg.jpg'));
    }

    public function test_home_faq_settings_do_not_change_the_faqs_page_banner(): void
    {
        $this->group('Asthma')->items()->create(['question' => 'Q?', 'answer' => 'A.', 'sort_order' => 1]);

        Livewire::actingAs($this->superAdmin(), 'admin')
            ->test('pages::admin.faqs.index')
            ->set('home_heading', 'Changed home heading')
            ->call('saveHomeBlock');

        $this->get(route('faq'))
            ->assertOk()
            ->assertSee('Read all the FAQs related to viral fever')
            ->assertDontSee('Changed home heading');
    }

    public function test_home_page_faq_block_uses_home_groups_only(): void
    {
        $home = $this->group('Home', 'home', 1);
        $home->items()->create(['question' => 'Home question?', 'answer' => 'Home answer.', 'sort_order' => 1]);

        $page = $this->group('Page', 'faq_page', 2);
        $page->items()->create(['question' => 'FAQs page question?', 'answer' => 'Page answer.', 'sort_order' => 1]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Home question?')
            ->assertDontSee('FAQs page question?');
    }
}
