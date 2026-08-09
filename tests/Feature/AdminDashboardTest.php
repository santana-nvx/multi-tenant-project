<?php

use App\Models\GatewaySetting;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

test('an admin can load the dashboard with the merchant balance and transaction stats', function () {
    $domain = 'dashboard-a.test';
    [$tenant, $admin] = $this->createTenantWithAdmin(['email' => 'admin@example.com', 'password' => 'password'], $domain);

    $tenant->run(function () {
        GatewaySetting::create([
            'gateway_name' => 'sandbox',
            'environment' => 'testing',
            'username' => 'test-merchant',
            'base_url' => 'https://fake-gateway.test',
            'api_key' => 'fake-api-key',
            'api_secret' => 'fake-api-secret',
            'is_active' => true,
        ]);

        $user = User::factory()->create();
        $wallet = $user->wallet()->create(['currency' => 'MYR', 'balance' => '0.00']);

        Transaction::create([
            'user_id' => $user->id,
            'wallet_id' => $wallet->id,
            'type' => 'deposit',
            'status' => 'success',
            'amount' => '300.00',
            'currency' => 'MYR',
            'order_id' => 'DP-DASH-1',
        ]);

        Transaction::create([
            'user_id' => $user->id,
            'wallet_id' => $wallet->id,
            'type' => 'withdrawal',
            'status' => 'success',
            'amount' => '100.00',
            'currency' => 'MYR',
            'order_id' => 'WTH-DASH-1',
        ]);

        Transaction::create([
            'user_id' => $user->id,
            'wallet_id' => $wallet->id,
            'type' => 'deposit',
            'status' => 'pending',
            'amount' => '50.00',
            'currency' => 'MYR',
            'order_id' => 'DP-DASH-2',
        ]);
    });

    Http::fake([
        'https://fake-gateway.test/merchant/auth' => Http::response(['status' => true, 'auth' => 'fake-token']),
        'https://fake-gateway.test/wallet/get_balance' => Http::response([
            'status' => true,
            'balance' => '9999.00',
            'currency' => 'MYR',
        ]),
    ]);

    $token = $this->post("http://{$domain}/admin/login", [
        'email' => 'admin@example.com',
        'password' => 'password',
    ])->assertOk()->json('access_token');

    $response = $this->withHeaders(['Authorization' => "Bearer {$token}"])
        ->getJson("http://{$domain}/admin/dashboard/stats");

    $response->assertOk()->assertJson([
        'merchant_balance' => [
            'status' => true,
            'balance' => '9999.00',
            'currency' => 'MYR',
        ],
        'stats' => [
            'total_deposits' => '300.00',
            'total_withdrawals' => '100.00',
            'pending_count' => 1,
        ],
    ]);
});

test('a request without a token is rejected on the admin dashboard', function () {
    $domain = 'dashboard-b.test';
    $this->createTenant($domain);

    $this->getJson("http://{$domain}/admin/dashboard/stats")->assertStatus(401);
});

test('a player token is rejected on the admin dashboard', function () {
    $domain = 'dashboard-c.test';
    $this->createTenantWithUser(['email' => 'player@example.com', 'password' => 'password'], $domain);

    $token = $this->post("http://{$domain}/api/login", [
        'email' => 'player@example.com',
        'password' => 'password',
    ])->json('access_token');

    $this->withHeaders(['Authorization' => "Bearer {$token}"])
        ->getJson("http://{$domain}/admin/dashboard/stats")
        ->assertStatus(401);
});
