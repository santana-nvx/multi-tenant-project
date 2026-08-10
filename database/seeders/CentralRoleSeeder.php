<?php

namespace Database\Seeders;

use App\Models\CentralAdmin;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class CentralRoleSeeder extends Seeder
{
    public function run(): void
    {
        $role = Role::firstOrCreate([
            'name' => 'central-admin',
            'guard_name' => 'central-admin',
        ]);

        $centralAdmin = CentralAdmin::firstOrCreate(
            ['email' => 'central-admin@multi-tenant-project.test'],
            ['name' => 'Central Admin', 'password' => 'password123']
        );

        $centralAdmin->assignRole($role);
    }
}
