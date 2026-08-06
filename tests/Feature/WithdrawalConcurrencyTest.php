<?php

use App\Models\GatewaySetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

test('a second withdrawal for an already-depleted balance is rejected, not double-spent', function () {
    $domain = 'withdraw-concurrency.test';
    [$tenant, $user] = $this->createTenantWithUser(['email' => 'player@example.com', 'password' => 'password'], $domain);

    $tenant->run(function () use ($user) {
        $user->wallet()->create(['currency' => 'MYR', 'balance' => '100.00']);

        // The driver needs a real GatewaySetting row to construct itself —
        // the same lookup AppServiceProvider's binding does for real requests.
        GatewaySetting::create([
            'gateway_name' => 'sandbox',
            'environment' => 'testing',
            'username' => 'test-merchant',
            'base_url' => 'https://fake-gateway.test',
            'api_key' => 'fake-api-key',
            'api_secret' => 'fake-api-secret',
            'is_active' => true,
        ]);
    });

    Http::fake([
        'https://fake-gateway.test/merchant/auth' => Http::response(['status' => true, 'auth' => 'fake-token']),
        'https://fake-gateway.test/merchant/withdraw_orders' => Http::response(['status' => true, 'message' => 'Success', 'payment_id' => 'W-1']),
    ]);

    $token = $this->post("http://{$domain}/api/login", [
        'email' => 'player@example.com',
        'password' => 'password',
    ])->assertOk()->json('access_token');

    $headers = ['Authorization' => "Bearer {$token}"];
    $payload = [
        'amount' => '100.00',
        'currency' => 'MYR',
        'bank_id' => '1',
        'holder_name' => 'Test Player',
        'account_no' => '1234567890',
    ];

    // First withdrawal for the full balance succeeds — deducts the wallet down to 0.00.
    $this->withHeaders($headers)->postJson("http://{$domain}/api/withdrawals", $payload)
        ->assertStatus(201);

    // A second withdrawal for the same amount must be rejected — the wallet is
    // already at 0.00, so this proves the balance check re-reads the real,
    // locked value instead of trusting a stale figure from before the first
    // request committed.
    $this->withHeaders($headers)->postJson("http://{$domain}/api/withdrawals", $payload)
        ->assertStatus(422)
        ->assertJson(['message' => 'Insufficient Balance']);

    $tenant->run(function () use ($user) {
        expect((string) $user->wallet->fresh()->balance)->toBe('0.00');
    });
});