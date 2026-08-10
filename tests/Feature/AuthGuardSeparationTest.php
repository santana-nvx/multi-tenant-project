<?php

use App\Models\Admin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;

uses(RefreshDatabase::class);

test('player can log in and access a player route', function () {
    $domain = 'guard-a.test';
    $this->createTenantWithUser(['email' => 'player@example.com', 'password' => 'password'], $domain);

    $token = $this->post("http://{$domain}/api/login", [
        'email' => 'player@example.com',
        'password' => 'password',
    ])->assertOk()->json('access_token');

    $this->withHeaders(['Authorization' => "Bearer {$token}"])
        ->get("http://{$domain}/api/me")
        ->assertOk()
        ->assertJson(['email' => 'player@example.com']);
});

test('admin can log in and access an admin route', function () {
    $domain = 'guard-b.test';
    $this->createTenantWithAdmin(['email' => 'admin@example.com', 'password' => 'password'], $domain);

    $token = $this->post("http://{$domain}/admin/login", [
        'email' => 'admin@example.com',
        'password' => 'password',
    ])->assertOk()->json('access_token');

    $this->withHeaders(['Authorization' => "Bearer {$token}"])
        ->get("http://{$domain}/admin/me")
        ->assertOk()
        ->assertJson(['email' => 'admin@example.com']);
});

test('a player token is rejected on admin routes', function () {
    $domain = 'guard-c.test';
    $this->createTenantWithUser(['email' => 'player@example.com', 'password' => 'password'], $domain);

    $token = $this->post("http://{$domain}/api/login", [
        'email' => 'player@example.com',
        'password' => 'password',
    ])->json('access_token');

    $this->withHeaders(['Authorization' => "Bearer {$token}"])
        ->get("http://{$domain}/admin/me")
        ->assertStatus(401);
});

test('an admin token is rejected on player routes', function () {
    $domain = 'guard-d.test';
    $this->createTenantWithAdmin(['email' => 'admin@example.com', 'password' => 'password'], $domain);

    $token = $this->post("http://{$domain}/admin/login", [
        'email' => 'admin@example.com',
        'password' => 'password',
    ])->json('access_token');

    $this->withHeaders(['Authorization' => "Bearer {$token}"])
        ->get("http://{$domain}/api/me")
        ->assertStatus(401);
});

test('login is independent for both guards in the same tenant', function () {
    $domain = 'guard-e.test';
    $tenant = $this->createTenant($domain);

    $tenant->run(function () {
        User::factory()->create(['email' => 'player@example.com', 'password' => 'password']);
        Admin::factory()->create(['email' => 'admin@example.com', 'password' => 'password']);
    });

    $this->post("http://{$domain}/api/login", ['email' => 'player@example.com', 'password' => 'password'])
        ->assertOk()->assertJsonStructure(['access_token', 'token_type']);

    $this->post("http://{$domain}/admin/login", ['email' => 'admin@example.com', 'password' => 'password'])
        ->assertOk()->assertJsonStructure(['access_token', 'token_type']);
});

test('an admin token from one tenant is rejected on another tenant\'s domain', function () {
    $domainA = 'cross-tenant-a.test';
    $domainB = 'cross-tenant-b.test';

    // Same email deliberately used in both tenants — this proves rejection
    // isn't just "no admin with this email", it's specifically "the admin ID
    // encoded in this token doesn't exist in tenant B's own database", since
    // each tenant's admin has a different ULID even with a matching email.
    $this->createTenantWithAdmin(['email' => 'admin@example.com', 'password' => 'password'], $domainA);
    $this->createTenantWithAdmin(['email' => 'admin@example.com', 'password' => 'password'], $domainB);

    $token = $this->post("http://{$domainA}/admin/login", [
        'email' => 'admin@example.com',
        'password' => 'password',
    ])->json('access_token');

    // Laravel's AuthManager caches the resolved 'admin' guard (and its
    // authenticated user) for the lifetime of the test's shared container —
    // without this, the request below would silently reuse the login
    // request's cached auth context instead of freshly resolving the token
    // against tenant B's own database connection. Not an issue in production
    // (every real request gets a fresh process), only when simulating two
    // different tenant-domain requests inside one Pest test.
    Auth::forgetGuards();

    $this->withHeaders(['Authorization' => "Bearer {$token}"])
        ->get("http://{$domainB}/admin/me")
        ->assertStatus(401);
});

test('a player token from one tenant is rejected on another tenant\'s domain', function () {
    $domainA = 'cross-tenant-c.test';
    $domainB = 'cross-tenant-d.test';

    $this->createTenantWithUser(['email' => 'player@example.com', 'password' => 'password'], $domainA);
    $this->createTenantWithUser(['email' => 'player@example.com', 'password' => 'password'], $domainB);

    $token = $this->post("http://{$domainA}/api/login", [
        'email' => 'player@example.com',
        'password' => 'password',
    ])->json('access_token');

    Auth::forgetGuards();

    $this->withHeaders(['Authorization' => "Bearer {$token}"])
        ->get("http://{$domainB}/api/me")
        ->assertStatus(401);
});
