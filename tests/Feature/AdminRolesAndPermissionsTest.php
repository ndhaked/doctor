<?php

namespace Tests\Feature;

use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminRolesAndPermissionsTest extends TestCase
{
    use RefreshDatabase;

    protected function seedRolesAndPermissions(): void
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }

    public function test_admin_without_permission_cannot_view_admins_page(): void
    {
        $this->seedRolesAndPermissions();

        $admin = Admin::factory()->create();
        $admin->assignRole('Admin');

        $this->actingAs($admin, 'admin')
            ->get(route('admin.admins.index'))
            ->assertForbidden();
    }

    public function test_super_admin_can_view_admins_and_roles_pages(): void
    {
        $this->seedRolesAndPermissions();

        $admin = Admin::factory()->create();
        $admin->assignRole('Super Admin');

        $this->actingAs($admin, 'admin')
            ->get(route('admin.admins.index'))
            ->assertOk()
            ->assertSee('Admins');

        $this->actingAs($admin, 'admin')
            ->get(route('admin.roles.index'))
            ->assertOk()
            ->assertSee('Roles');
    }

    public function test_super_admin_can_create_an_admin_with_a_role(): void
    {
        $this->seedRolesAndPermissions();

        $superAdmin = Admin::factory()->create();
        $superAdmin->assignRole('Super Admin');

        Livewire::actingAs($superAdmin, 'admin')
            ->test('pages::admin.admins.index')
            ->call('create')
            ->set('name', 'New Admin')
            ->set('email', 'newadmin@doctorweb.test')
            ->set('password', 'password123')
            ->set('selectedRoles', ['Admin'])
            ->call('save');

        $this->assertDatabaseHas('admins', ['email' => 'newadmin@doctorweb.test']);

        $created = Admin::where('email', 'newadmin@doctorweb.test')->first();
        $this->assertTrue($created->hasRole('Admin'));
    }

    public function test_super_admin_cannot_delete_own_account(): void
    {
        $this->seedRolesAndPermissions();

        $superAdmin = Admin::factory()->create();
        $superAdmin->assignRole('Super Admin');

        Livewire::actingAs($superAdmin, 'admin')
            ->test('pages::admin.admins.index')
            ->call('delete', $superAdmin->id)
            ->assertHasErrors('delete');

        $this->assertDatabaseHas('admins', ['id' => $superAdmin->id]);
    }

    public function test_super_admin_role_cannot_be_deleted(): void
    {
        $this->seedRolesAndPermissions();

        $superAdmin = Admin::factory()->create();
        $superAdmin->assignRole('Super Admin');

        $role = Role::where('name', 'Super Admin')->where('guard_name', 'admin')->first();

        Livewire::actingAs($superAdmin, 'admin')
            ->test('pages::admin.roles.index')
            ->call('delete', $role->id)
            ->assertHasErrors('delete');

        $this->assertDatabaseHas('roles', ['id' => $role->id]);
    }

    public function test_super_admin_can_create_a_role_with_permissions(): void
    {
        $this->seedRolesAndPermissions();

        $superAdmin = Admin::factory()->create();
        $superAdmin->assignRole('Super Admin');

        Livewire::actingAs($superAdmin, 'admin')
            ->test('pages::admin.roles.index')
            ->call('create')
            ->set('name', 'Editor')
            ->set('selectedPermissions', ['manage admins'])
            ->call('save');

        $this->assertDatabaseHas('roles', ['name' => 'Editor', 'guard_name' => 'admin']);

        $role = Role::where('name', 'Editor')->first();
        $this->assertTrue($role->hasPermissionTo('manage admins'));
    }
}
