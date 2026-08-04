<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('tenant data is isolated between tenants', function () {
    [$tenantA] = $this->createTenantWithUser(['email' => 'only-in-a@example.com']);
    [$tenantB] = $this->createTenantWithUser(['email' => 'only-in-b@example.com']);

    $tenantA->run(function () {
        expect(User::where('email', 'only-in-a@example.com')->exists())->toBeTrue();
        expect(User::where('email', 'only-in-b@example.com')->exists())->toBeFalse();
    });

    $tenantB->run(function () {
        expect(User::where('email', 'only-in-b@example.com')->exists())->toBeTrue();
        expect(User::where('email', 'only-in-a@example.com')->exists())->toBeFalse();
    });
});

test('tenant is resolved by domain', function () {
    $tenant = $this->createTenant('tenant-a.test');

    $response = $this->get('http://tenant-a.test/tenant-check');

    $response->assertOk();
    $response->assertJson(['tenant_id' => $tenant->id]);
});

test('tenant is resolved by x-tenant header when the domain does not match any tenant', function () {
    $tenant = $this->createTenant();

    $response = $this->withHeaders(['X-Tenant' => $tenant->id])
        ->get('http://127.0.0.1/tenant-check');

    $response->assertOk();
    $response->assertJson(['tenant_id' => $tenant->id]);
});

test('request is rejected when tenant cannot be identified by domain or header', function () {
    $response = $this->get('http://127.0.0.1/tenant-check');

    $response->assertStatus(500);
});
