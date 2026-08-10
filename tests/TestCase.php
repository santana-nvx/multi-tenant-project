<?php

namespace Tests;

use App\Models\Admin;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Stancl\Tenancy\Tenancy;

abstract class TestCase extends BaseTestCase
{
    protected function createTenant(?string $domain = null): Tenant
    {
        $tenant = Tenant::create();

        $tenant->domains()->create([
            'domain' => $domain ?? Str::random(10).'.test',
        ]);

        return $tenant;
    }

    protected function createTenantWithUser(array $userAttributes = [], ?string $domain = null): array
    {
        $tenant = $this->createTenant($domain);

        $user = $tenant->run(function () use ($userAttributes) {
            $user = User::factory()->create($userAttributes);

            $role = Role::firstOrCreate(['name' => 'end-user', 'guard_name' => 'api']);
            $user->assignRole($role);

            return $user;
        });

        return [$tenant, $user];
    }

    protected function createTenantWithAdmin(array $adminAttributes = [], ?string $domain = null): array
    {
        $tenant = $this->createTenant($domain);

        $admin = $tenant->run(function () use ($adminAttributes) {
            $admin = Admin::factory()->create($adminAttributes);

            $role = Role::firstOrCreate(['name' => 'tenant-admin', 'guard_name' => 'admin']);
            $admin->assignRole($role);

            return $admin;
        });

        return [$tenant, $admin];
    }

    protected function tearDown(): void
    {
        if (app()->bound(Tenancy::class) && tenancy()->initialized) {
            tenancy()->end();
        }

        foreach (glob(database_path('tenant*')) as $file) {
            @unlink($file);
        }

        parent::tearDown();
    }
}
