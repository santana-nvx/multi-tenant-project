<?php

namespace Tests;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Str;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }

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

    protected function tearDown(): void
    {
        if (app()->bound(\Stancl\Tenancy\Tenancy::class) && tenancy()->initialized) {
            tenancy()->end();
        }

        foreach (glob(database_path('tenant*')) as $file) {
            @unlink($file);
        }

        parent::tearDown();
    }
}
