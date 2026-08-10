<?php

use App\Models\CentralAdmin;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

test('a central admin can load platform-wide dashboard stats', function () {
    Role::firstOrCreate(['name' => 'central-admin', 'guard_name' => 'central-admin']);
    $centralAdmin = CentralAdmin::create(['name' => 'Central Admin', 'email' => 'central-admin@example.com', 'password' => 'password']);
    $centralAdmin->assignRole('central-admin');

    [$tenant] = $this->createTenantWithUser(['email' => 'player@example.com'], 'dashboard-tenant.test');

    $tenant->run(function () {
        $user = User::where('email', 'player@example.com')->first();
        $wallet = $user->wallet()->create(['currency' => 'MYR', 'balance' => '0.00']);

        Transaction::create([
            'user_id' => $user->id,
            'wallet_id' => $wallet->id,
            'type' => 'deposit',
            'status' => 'success',
            'amount' => '200.00',
            'currency' => 'MYR',
            'order_id' => 'DP-CENTRAL-DASH-1',
        ]);
    });

    $token = $this->post('/central-admin/login', [
        'email' => 'central-admin@example.com',
        'password' => 'password',
    ])->json('access_token');

    $response = $this->withHeaders(['Authorization' => "Bearer {$token}"])
        ->getJson('/central-admin/dashboard/stats');

    $response->assertOk();
    expect($response->json('tenant_count'))->toBe(1);
    expect($response->json('total_deposits'))->toBe('200.00');
    expect($response->json('total_withdrawals'))->toBe('0.00');
    expect($response->json('recent_activity.0.tenant_domain'))->toBe('dashboard-tenant.test');
});

test('a request without a token is rejected on the central dashboard', function () {
    $this->getJson('/central-admin/dashboard/stats')->assertStatus(401);
});

test('a tenant admin token is rejected on the central dashboard', function () {
    $domain = 'central-dash-guard.test';
    $this->createTenantWithAdmin(['email' => 'admin@example.com', 'password' => 'password'], $domain);

    $token = $this->post("http://{$domain}/admin/login", [
        'email' => 'admin@example.com',
        'password' => 'password',
    ])->json('access_token');

    $this->withHeaders(['Authorization' => "Bearer {$token}"])
        ->getJson('/central-admin/dashboard/stats')
        ->assertStatus(401);
});
