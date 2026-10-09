<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\GalleryGroup;
use App\Models\GalleryImage;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class GalleryManagerTest extends TestCase
{
    use RefreshDatabase;

    protected function superAdmin(): Admin
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $admin = Admin::factory()->create();
        $admin->assignRole('Super Admin');

        return $admin;
    }

    protected function group(string $title, array $captions = [], bool $active = true): GalleryGroup
    {
        $group = GalleryGroup::create(['title' => $title, 'is_active' => $active, 'sort_order' => GalleryGroup::count() + 1]);

        foreach ($captions as $i => $caption) {
            $group->images()->create(['image' => 'images/hospital.webp', 'caption' => $caption, 'sort_order' => $i + 1]);
        }

        return $group;
    }

    public function test_page_shows_active_groups_and_images_only(): void
    {
        $this->group('Awards Group', ['Gold medal shot', 'Silver cup shot']);
        $this->group('Hidden Group', ['Secret pic'], active: false);
        $shown = $this->group('Mixed Group', ['Visible pic']);
        $shown->images()->create(['image' => 'images/hospital.webp', 'caption' => 'Hidden pic', 'is_active' => false]);
        $this->group('Empty Group');

        $this->get(route('gallery'))
            ->assertOk()
            ->assertSee('Image Gallery')
            ->assertSee('Awards Group')->assertSee('Gold medal shot')->assertSee('Silver cup shot')
            ->assertSee('Visible pic')
            ->assertDontSee('Hidden Group')->assertDontSee('Secret pic')
            ->assertDontSee('Hidden pic')
            ->assertDontSee('Empty Group');
    }

    public function test_page_texts_come_from_settings_and_menus_link_to_the_page(): void
    {
        Setting::put('gallery.title', 'Our Photo Wall');

        $this->get(route('gallery'))->assertOk()->assertSee('Our Photo Wall');
        $this->get('/')->assertSee(route('gallery'), false);
    }

    public function test_page_works_with_no_photos(): void
    {
        $this->get(route('gallery'))->assertOk()->assertSee('Photos will be added soon.');
    }

    public function test_admin_without_permission_is_forbidden(): void
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $admin = Admin::factory()->create();
        $admin->assignRole('Admin');

        $this->actingAs($admin, 'admin')->get(route('admin.gallery.index'))->assertForbidden();
    }

    public function test_admin_manages_groups(): void
    {
        $this->actingAs($this->superAdmin(), 'admin');

        Livewire::test('pages::admin.gallery.index')
            ->call('createGroup')->set('group_title', '')->call('saveGroup')->assertHasErrors(['group_title'])
            ->set('group_title', 'Awards')->call('saveGroup')->assertHasNoErrors()
            ->call('createGroup')->set('group_title', 'Clinic')->call('saveGroup');

        $awards = GalleryGroup::where('title', 'Awards')->first();
        $clinic = GalleryGroup::where('title', 'Clinic')->first();

        $c = Livewire::test('pages::admin.gallery.index')
            ->call('editGroup', $awards->id)->set('group_title', 'Prizes')->call('saveGroup')
            ->call('toggleGroup', $awards->id)
            ->call('moveGroup', $clinic->id, 'up');

        $this->assertSame('Prizes', $awards->fresh()->title);
        $this->assertFalse($awards->fresh()->is_active);
        $this->assertTrue($clinic->fresh()->sort_order < $awards->fresh()->sort_order);

        $c->call('deleteGroup', $awards->id);
        $this->assertDatabaseMissing('gallery_groups', ['id' => $awards->id]);
    }

    public function test_admin_uploads_edits_and_deletes_images(): void
    {
        $uploads = public_path('uploads');
        $group = $this->group('Awards');
        $this->actingAs($this->superAdmin(), 'admin');

        $c = Livewire::test('pages::admin.gallery.index')
            ->call('selectGroup', $group->id)
            ->set('uploads', [UploadedFile::fake()->image('a.jpg', 600, 400), UploadedFile::fake()->image('b.png', 600, 400)])
            ->call('addImages')->assertHasNoErrors();

        $images = $group->images()->get();
        $this->assertCount(2, $images);
        $paths = $images->pluck('image');
        $paths->each(fn ($p) => $this->assertFileExists(public_path($p)));

        $first = $images[0];
        $c->call('editImage', $first->id)->set('image_caption', 'Gold medal')->call('saveImage')
            ->call('toggleImage', $first->id)
            ->call('moveImage', $first->id, 'down');

        $this->assertSame('Gold medal', $first->fresh()->caption);
        $this->assertFalse($first->fresh()->is_active);
        $this->assertTrue($first->fresh()->sort_order > $images[1]->fresh()->sort_order);

        $c->call('deleteImage', $images[1]->id);
        $this->assertDatabaseMissing('gallery_images', ['id' => $images[1]->id]);
        $this->assertFileDoesNotExist(public_path($images[1]->image));

        // cleanup the remaining test upload
        $c->call('deleteGroup', $group->id);
        $this->assertFileDoesNotExist(public_path($first->image));
    }

    public function test_upload_validation(): void
    {
        $group = $this->group('Awards');
        $this->actingAs($this->superAdmin(), 'admin');

        Livewire::test('pages::admin.gallery.index')
            ->call('selectGroup', $group->id)
            ->call('addImages')->assertHasErrors(['uploads'])
            ->set('uploads', [UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf')])
            ->call('addImages')->assertHasErrors(['uploads.0']);

        $this->assertSame(0, GalleryImage::count());
    }

    public function test_page_texts_can_be_saved(): void
    {
        $this->actingAs($this->superAdmin(), 'admin');

        Livewire::test('pages::admin.gallery.index')
            ->set('page_title', '')->call('savePage')->assertHasErrors(['page_title'])
            ->set('page_title', 'Photo Wall')->set('page_intro', 'Look around')->call('savePage')->assertHasNoErrors();

        $this->get(route('gallery'))->assertSee('Photo Wall')->assertSee('Look around');
    }
}
