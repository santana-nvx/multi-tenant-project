<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\Tenant;
use App\Models\User;
use Spatie\Permission\Models\Role;

class TenantCreationService
{
    public function create(
        string $domain,
        string $tenantName,
        string $userName,
        string $userEmail,
        string $userPassword,
        string $adminName,
        string $adminEmail,
        string $adminPassword,
    ): Tenant {
        $tenant = Tenant::create([
            'name' => $tenantName,
            'is_active' => true,
        ]);

        $tenant->domains()->create(['domain' => $domain]);

        $tenant->run(function () use ($userName, $userEmail, $userPassword, $adminName, $adminEmail, $adminPassword) {
            $user = User::create([
                'name' => $userName,
                'email' => $userEmail,
                'password' => $userPassword,
            ]);

            $admin = Admin::create([
                'name' => $adminName,
                'email' => $adminEmail,
                'password' => $adminPassword,
            ]);

            $adminRole = Role::firstOrCreate(['name' => 'tenant-admin', 'guard_name' => 'admin']);
            $userRole = Role::firstOrCreate(['name' => 'end-user', 'guard_name' => 'api']);

            $admin->assignRole($adminRole);
            $user->assignRole($userRole);
        });

        return $tenant;
    }
}
