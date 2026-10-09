<?php

namespace Database\Seeders;

use App\Models\Admin;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $admin = Admin::updateOrCreate(
            ['email' => 'admin@doctorweb.test'],
            [
                'name' => 'Administrator',
                'password' => 'password',
            ]
        );

        $admin->syncRoles(['Super Admin']);
    }
}
