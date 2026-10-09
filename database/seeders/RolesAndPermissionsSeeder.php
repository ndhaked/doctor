<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissions = [
            'manage admins',
            'manage roles',
            'manage slider',
            'manage blog',
            'manage faqs',
            'manage symptoms',
            'manage services',
            'manage clinic',
            'manage intro',
            'manage why choose',
            'manage doctor',
            'manage doctor card',
            'manage site',
            'manage appointment',
            'manage reviews',
            'manage strip',
            'manage associate',
            'manage about page',
            'manage contact page',
            'manage enquiries',
            'manage gallery',
            'manage videos',

            // Clinic management: patient, medical, appointment, financial and staff data are separate permissions
            'view clinic dashboard',
            'manage clinic profile',
            'manage patients',
            'view medical records',
            'manage appointments',
            'manage consultations',
            'manage billing',
            'manage staff',
            'view reports',
            'manage clinic settings',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'admin']);
        }

        $superAdmin = Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'admin']);
        $superAdmin->syncPermissions($permissions);

        Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'admin']);

        // Starting roles for the clinic. They are only filled when first created, so later changes made in the admin are kept.
        $clinicRoles = [
            'Doctor' => ['view clinic dashboard', 'manage clinic profile', 'manage patients', 'view medical records', 'manage appointments', 'manage consultations', 'manage billing', 'manage staff', 'view reports', 'manage clinic settings'],
            'Receptionist' => ['view clinic dashboard', 'manage patients', 'manage appointments', 'manage billing'],
            'Nurse' => ['view clinic dashboard', 'manage patients', 'view medical records', 'manage appointments'],
        ];

        foreach ($clinicRoles as $name => $perms) {
            $role = Role::firstOrCreate(['name' => $name, 'guard_name' => 'admin']);

            if ($role->wasRecentlyCreated) {
                $role->syncPermissions($perms);
            }
        }
    }
}
