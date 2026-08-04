<?php

use App\Models\Admin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

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
