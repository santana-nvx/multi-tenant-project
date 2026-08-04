<?php

namespace Tests;

use App\Models\Admin;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Str;
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

        $user = $tenant->run(fn () => User::factory()->create($userAttributes));

        return [$tenant, $user];
    }

    protected function createTenantWithAdmin(array $adminAttributes = [], ?string $domain = null): array
    {
        $tenant = $this->createTenant($domain);

        $admin = $tenant->run(fn () => Admin::factory()->create($adminAttributes));

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
