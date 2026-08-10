<?php

use App\Models\Admin;
use App\Models\CentralAdmin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Stancl\Tenancy\Database\Models\Domain;

uses(RefreshDatabase::class);

test('a central admin can create a tenant, provisioning its domain, admin, and user', function () {
    Role::firstOrCreate(['name' => 'central-admin', 'guard_name' => 'central-admin']);
    $centralAdmin = CentralAdmin::create(['name' => 'Central Admin', 'email' => 'central-admin@example.com', 'password' => 'password']);
    $centralAdmin->assignRole('central-admin');

    $token = $this->post('/central-admin/login', [
        'email' => 'central-admin@example.com',
        'password' => 'password',
    ])->json('access_token');

    $response = $this->withHeaders(['Authorization' => "Bearer {$token}"])
        ->postJson('/central-admin/tenants', [
            'domain' => 'newco.test',
            'name' => 'New Co',
            'user_name' => 'Player',
            'user_email' => 'player@newco.test',
            'user_password' => 'password123',
            'admin_name' => 'Admin',
            'admin_email' => 'admin@newco.test',
            'admin_password' => 'password123',
        ]);

    $response->assertStatus(201);

    $domain = Domain::where('domain', 'newco.test')->first();
    expect($domain)->not->toBeNull();

    $domain->tenant->run(function () {
        expect(Admin::where('email', 'admin@newco.test')->exists())->toBeTrue();
        expect(User::where('email', 'player@newco.test')->exists())->toBeTrue();

        $admin = Admin::where('email', 'admin@newco.test')->first();
        expect($admin->getRoleNames()->toArray())->toBe(['tenant-admin']);
    });
});

test('a central admin can list tenants filtered by domain search', function () {
    Role::firstOrCreate(['name' => 'central-admin', 'guard_name' => 'central-admin']);
    $centralAdmin = CentralAdmin::create(['name' => 'Central Admin', 'email' => 'central-admin@example.com', 'password' => 'password']);
    $centralAdmin->assignRole('central-admin');

    $token = $this->post('/central-admin/login', [
        'email' => 'central-admin@example.com',
        'password' => 'password',
    ])->json('access_token');

    $this->createTenant('findme.test');
    $this->createTenant('other.test');

    $response = $this->withHeaders(['Authorization' => "Bearer {$token}"])
        ->getJson('/central-admin/tenants?search=findme');

    $response->assertOk();
    expect($response->json('total'))->toBe(1);
    expect($response->json('data.0.domain'))->toBe('findme.test');
});

test('a central admin can filter tenants by active/inactive status', function () {
    Role::firstOrCreate(['name' => 'central-admin', 'guard_name' => 'central-admin']);
    $centralAdmin = CentralAdmin::create(['name' => 'Central Admin', 'email' => 'central-admin@example.com', 'password' => 'password']);
    $centralAdmin->assignRole('central-admin');

    $token = $this->post('/central-admin/login', [
        'email' => 'central-admin@example.com',
        'password' => 'password',
    ])->json('access_token');

    // Never touched — is_active is null, which counts as "active".
    $this->createTenant('never-touched.test');

    $inactiveTenant = $this->createTenant('explicitly-inactive.test');
    $inactiveTenant->update(['is_active' => false]);

    $activeResponse = $this->withHeaders(['Authorization' => "Bearer {$token}"])
        ->getJson('/central-admin/tenants?status=active');
    $activeDomains = collect($activeResponse->json('data'))->pluck('domain');

    expect($activeDomains)->toContain('never-touched.test');
    expect($activeDomains)->not->toContain('explicitly-inactive.test');

    $inactiveResponse = $this->withHeaders(['Authorization' => "Bearer {$token}"])
        ->getJson('/central-admin/tenants?status=inactive');
    $inactiveDomains = collect($inactiveResponse->json('data'))->pluck('domain');

    expect($inactiveDomains)->toContain('explicitly-inactive.test');
    expect($inactiveDomains)->not->toContain('never-touched.test');
});

test('a central admin can update a tenant name', function () {
    Role::firstOrCreate(['name' => 'central-admin', 'guard_name' => 'central-admin']);
    $centralAdmin = CentralAdmin::create(['name' => 'Central Admin', 'email' => 'central-admin@example.com', 'password' => 'password']);
    $centralAdmin->assignRole('central-admin');

    $token = $this->post('/central-admin/login', [
        'email' => 'central-admin@example.com',
        'password' => 'password',
    ])->json('access_token');

    $tenant = $this->createTenant('rename-me.test');

    $response = $this->withHeaders(['Authorization' => "Bearer {$token}"])
        ->putJson("/central-admin/tenants/{$tenant->id}", ['name' => 'Renamed Co']);

    $response->assertOk()->assertJson(['name' => 'Renamed Co']);
});

test('deactivating a tenant blocks access to its domain, and reactivating restores it', function () {
    Role::firstOrCreate(['name' => 'central-admin', 'guard_name' => 'central-admin']);
    $centralAdmin = CentralAdmin::create(['name' => 'Central Admin', 'email' => 'central-admin@example.com', 'password' => 'password']);
    $centralAdmin->assignRole('central-admin');

    $token = $this->post('/central-admin/login', [
        'email' => 'central-admin@example.com',
        'password' => 'password',
    ])->json('access_token');

    $tenant = $this->createTenant('togglable.test');

    $this->withHeaders(['Authorization' => "Bearer {$token}"])
        ->patchJson("/central-admin/tenants/{$tenant->id}/deactivate")
        ->assertOk()
        ->assertJson(['is_active' => false]);

    $this->get('http://togglable.test/tenant-check')->assertStatus(403);

    tenancy()->end();

    $this->withHeaders(['Authorization' => "Bearer {$token}"])
        ->patchJson("/central-admin/tenants/{$tenant->id}/deactivate")
        ->assertOk()
        ->assertJson(['is_active' => true]);

    $this->get('http://togglable.test/tenant-check')->assertStatus(200);
});

test('a tenant admin token is rejected on central tenant management routes', function () {
    $domain = 'tm-guard.test';
    $this->createTenantWithAdmin(['email' => 'admin@example.com', 'password' => 'password'], $domain);

    $token = $this->post("http://{$domain}/admin/login", [
        'email' => 'admin@example.com',
        'password' => 'password',
    ])->json('access_token');

    $this->withHeaders(['Authorization' => "Bearer {$token}"])
        ->getJson('/central-admin/tenants')
        ->assertStatus(401);
});

test('a request without a token is rejected on central tenant management routes', function () {
    $this->getJson('/central-admin/tenants')->assertStatus(401);
});
