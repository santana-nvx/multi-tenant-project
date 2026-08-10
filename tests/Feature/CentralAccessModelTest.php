<?php

use App\Models\CentralAdmin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

test('a central admin can log in and access its own protected route', function () {
    Role::firstOrCreate(['name' => 'central-admin', 'guard_name' => 'central-admin']);

    $centralAdmin = CentralAdmin::create([
        'name' => 'Central Admin',
        'email' => 'central-admin@example.com',
        'password' => 'password',
    ]);
    $centralAdmin->assignRole('central-admin');

    $token = $this->post('/central-admin/login', [
        'email' => 'central-admin@example.com',
        'password' => 'password',
    ])->assertOk()->json('access_token');

    $this->withHeaders(['Authorization' => "Bearer {$token}"])
        ->getJson('/central-admin/me')
        ->assertOk()
        ->assertJson([
            'email' => 'central-admin@example.com',
            'roles' => ['central-admin'],
        ]);
});

test('a tenant admin token is rejected on the central admin route', function () {
    $domain = 'central-a.test';
    $this->createTenantWithAdmin(['email' => 'admin@example.com', 'password' => 'password'], $domain);

    $token = $this->post("http://{$domain}/admin/login", [
        'email' => 'admin@example.com',
        'password' => 'password',
    ])->json('access_token');

    $this->withHeaders(['Authorization' => "Bearer {$token}"])
        ->getJson('/central-admin/me')
        ->assertStatus(401);
});

test('a central admin token is rejected on tenant routes', function () {
    Role::firstOrCreate(['name' => 'central-admin', 'guard_name' => 'central-admin']);

    $centralAdmin = CentralAdmin::create([
        'name' => 'Central Admin',
        'email' => 'central-admin@example.com',
        'password' => 'password',
    ]);
    $centralAdmin->assignRole('central-admin');

    $token = $this->post('/central-admin/login', [
        'email' => 'central-admin@example.com',
        'password' => 'password',
    ])->json('access_token');

    $domain = 'central-b.test';
    $this->createTenantWithUser(['email' => 'player@example.com', 'password' => 'password'], $domain);

    $this->withHeaders(['Authorization' => "Bearer {$token}"])
        ->getJson("http://{$domain}/api/me")
        ->assertStatus(401);

    $this->withHeaders(['Authorization' => "Bearer {$token}"])
        ->getJson("http://{$domain}/admin/me")
        ->assertStatus(401);
});
