<?php

namespace Tests\Feature;

use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class AdminProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_profile_page(): void
    {
        $admin = Admin::factory()->create();

        $this->actingAs($admin, 'admin')
            ->get(route('admin.profile'))
            ->assertOk()
            ->assertSee('Profile Information')
            ->assertSee('Update Password')
            ->assertSee($admin->email);
    }

    public function test_admin_can_update_their_name(): void
    {
        $admin = Admin::factory()->create(['name' => 'Old Name']);

        Livewire::actingAs($admin, 'admin')
            ->test('pages::admin.profile')
            ->set('name', 'New Name')
            ->call('updateProfileInformation')
            ->assertHasNoErrors();

        $this->assertSame('New Name', $admin->fresh()->name);
    }

    public function test_admin_can_update_their_password_with_correct_current_password(): void
    {
        $admin = Admin::factory()->create(['password' => Hash::make('old-password')]);

        Livewire::actingAs($admin, 'admin')
            ->test('pages::admin.profile')
            ->set('current_password', 'old-password')
            ->set('password', 'new-password-123')
            ->set('password_confirmation', 'new-password-123')
            ->call('updatePassword')
            ->assertHasNoErrors();

        $this->assertTrue(Hash::check('new-password-123', $admin->fresh()->password));
    }

    public function test_admin_cannot_update_password_with_wrong_current_password(): void
    {
        $admin = Admin::factory()->create(['password' => Hash::make('old-password')]);

        Livewire::actingAs($admin, 'admin')
            ->test('pages::admin.profile')
            ->set('current_password', 'wrong-password')
            ->set('password', 'new-password-123')
            ->set('password_confirmation', 'new-password-123')
            ->call('updatePassword')
            ->assertHasErrors('current_password');

        $this->assertTrue(Hash::check('old-password', $admin->fresh()->password));
    }

    public function test_admin_can_delete_their_own_account_when_another_admin_exists(): void
    {
        Admin::factory()->create();
        $admin = Admin::factory()->create(['password' => Hash::make('secret-password')]);

        Livewire::actingAs($admin, 'admin')
            ->test('pages::admin.profile')
            ->call('confirmAccountDeletion')
            ->set('delete_password', 'secret-password')
            ->call('deleteAccount')
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('admins', ['id' => $admin->id]);
        $this->assertGuest('admin');
    }

    public function test_admin_cannot_delete_account_with_wrong_password(): void
    {
        Admin::factory()->create();
        $admin = Admin::factory()->create(['password' => Hash::make('secret-password')]);

        Livewire::actingAs($admin, 'admin')
            ->test('pages::admin.profile')
            ->call('confirmAccountDeletion')
            ->set('delete_password', 'wrong-password')
            ->call('deleteAccount')
            ->assertHasErrors('delete_password');

        $this->assertDatabaseHas('admins', ['id' => $admin->id]);
    }

    public function test_the_only_remaining_admin_cannot_delete_their_account(): void
    {
        $admin = Admin::factory()->create(['password' => Hash::make('secret-password')]);

        Livewire::actingAs($admin, 'admin')
            ->test('pages::admin.profile')
            ->call('confirmAccountDeletion')
            ->set('delete_password', 'secret-password')
            ->call('deleteAccount')
            ->assertHasErrors('delete_password');

        $this->assertDatabaseHas('admins', ['id' => $admin->id]);
    }
}
