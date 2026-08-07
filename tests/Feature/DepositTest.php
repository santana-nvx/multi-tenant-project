<?php

use App\Models\GatewaySetting;
use App\Models\Transaction;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

test('a valid deposit creates a processing transaction and returns a payment url', function () {
    $domain = 'deposit-a.test';
    [$tenant, $user] = $this->createTenantWithUser(['email' => 'player@example.com', 'password' => 'password'], $domain);

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
    });

    $token = $this->post("http://{$domain}/api/login", [
        'email' => 'player@example.com',
        'password' => 'password',
    ])->assertOk()->json('access_token');

    $headers = ['Authorization' => "Bearer {$token}"];

    Http::fake([
        'https://fake-gateway.test/merchant/currency' => Http::response([
            'status' => true,
            'rate' => [['currency' => 'MYR', 'min' => 420.0000, 'max' => 42000.0000]],
        ]),
        'https://fake-gateway.test/merchant/auth' => Http::response(['status' => true, 'auth' => 'fake-token']),
        'https://fake-gateway.test/merchant/generate_orders' => Http::response([
            'status' => true,
            'p_url' => 'https://fake-gateway.test/pay/123',
            'payment_id' => 'PID-123',
        ]),
    ]);

    $response = $this->withHeaders($headers)->postJson("http://{$domain}/api/deposits", [
        'amount' => '500.00',
        'currency' => 'MYR',
        'pay_method' => 'online_banking',
        'bank_id' => '88',
        'phone_number' => '0123456789',
    ]);

    $response->assertStatus(201)->assertJson(['payment_url' => 'https://fake-gateway.test/pay/123']);

    $tenant->run(function () use ($user) {
        $transaction = $user->transactions()->latest()->first();

        expect($transaction->status->value)->toBe('processing');
        expect((string) $transaction->amount)->toBe('500.00');
        expect($transaction->currency)->toBe('MYR');
        expect($transaction->payment_gateway_id)->toBe('PID-123');
    });

    Http::assertSent(fn ($request) => $request->url() === 'https://fake-gateway.test/merchant/generate_orders'
        && collect($request->data())->firstWhere('name', 'amount')['contents'] === '500.00');
});

test('an amount outside the live gateway limits is rejected before any transaction is created', function () {
    $domain = 'deposit-b.test';
    [$tenant, $user] = $this->createTenantWithUser(['email' => 'player@example.com', 'password' => 'password'], $domain);

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
    });

    $token = $this->post("http://{$domain}/api/login", [
        'email' => 'player@example.com',
        'password' => 'password',
    ])->assertOk()->json('access_token');

    $headers = ['Authorization' => "Bearer {$token}"];

    Http::fake([
        'https://fake-gateway.test/merchant/currency' => Http::response([
            'status' => true,
            'rate' => [['currency' => 'MYR', 'min' => 420.0000, 'max' => 42000.0000]],
        ]),
    ]);

    $response = $this->withHeaders($headers)->postJson("http://{$domain}/api/deposits", [
        'amount' => '10.00',
        'currency' => 'MYR',
        'pay_method' => 'online_banking',
        'bank_id' => '88',
        'phone_number' => '0123456789',
    ]);

    $response->assertStatus(422);

    $tenant->run(function () use ($user) {
        expect($user->transactions()->count())->toBe(0);
    });

    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'generate_orders'));
});

test('a gateway auth failure marks the transaction failed, not stuck pending', function () {
    $domain = 'deposit-c.test';
    [$tenant, $user] = $this->createTenantWithUser(['email' => 'player@example.com', 'password' => 'password'], $domain);

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
    });

    $token = $this->post("http://{$domain}/api/login", [
        'email' => 'player@example.com',
        'password' => 'password',
    ])->assertOk()->json('access_token');

    $headers = ['Authorization' => "Bearer {$token}"];

    Http::fake([
        'https://fake-gateway.test/merchant/currency' => Http::response([
            'status' => true,
            'rate' => [['currency' => 'MYR', 'min' => 420.0000, 'max' => 42000.0000]],
        ]),
        'https://fake-gateway.test/merchant/auth' => Http::response(['status' => false, 'message' => 'Params incorrect']),
    ]);

    $this->withHeaders($headers)->postJson("http://{$domain}/api/deposits", [
        'amount' => '500.00',
        'currency' => 'MYR',
        'pay_method' => 'online_banking',
        'bank_id' => '88',
        'phone_number' => '0123456789',
    ])->assertStatus(422);

    $tenant->run(function () use ($user) {
        $transaction = $user->transactions()->latest()->first();

        expect($transaction)->not->toBeNull();
        expect($transaction->status->value)->toBe('failed');
    });
});

test('a generate_orders rejection marks the transaction failed, not stuck pending', function () {
    $domain = 'deposit-d.test';
    [$tenant, $user] = $this->createTenantWithUser(['email' => 'player@example.com', 'password' => 'password'], $domain);

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
    });

    $token = $this->post("http://{$domain}/api/login", [
        'email' => 'player@example.com',
        'password' => 'password',
    ])->assertOk()->json('access_token');

    $headers = ['Authorization' => "Bearer {$token}"];

    Http::fake([
        'https://fake-gateway.test/merchant/currency' => Http::response([
            'status' => true,
            'rate' => [['currency' => 'MYR', 'min' => 420.0000, 'max' => 42000.0000]],
        ]),
        'https://fake-gateway.test/merchant/auth' => Http::response(['status' => true, 'auth' => 'fake-token']),
        'https://fake-gateway.test/merchant/generate_orders' => Http::response(['status' => false, 'message' => 'Invalid Amount.']),
    ]);

    $this->withHeaders($headers)->postJson("http://{$domain}/api/deposits", [
        'amount' => '500.00',
        'currency' => 'MYR',
        'pay_method' => 'online_banking',
        'bank_id' => '88',
        'phone_number' => '0123456789',
    ])->assertStatus(422);

    $tenant->run(function () use ($user) {
        $transaction = $user->transactions()->latest()->first();

        expect($transaction->status->value)->toBe('failed');
    });
});

test('a deposit request without a token is rejected', function () {
    $domain = 'deposit-e.test';
    $this->createTenant($domain);

    $this->postJson("http://{$domain}/api/deposits", [
        'amount' => '500.00',
        'currency' => 'MYR',
        'pay_method' => 'online_banking',
        'bank_id' => '88',
        'phone_number' => '0123456789',
    ])->assertStatus(401);
});

test('order_id uniqueness is enforced at the database level', function () {
    $domain = 'deposit-f.test';
    [$tenant, $user] = $this->createTenantWithUser(['email' => 'player@example.com', 'password' => 'password'], $domain);

    $tenant->run(function () use ($user) {
        $wallet = $user->wallet()->create(['currency' => 'MYR', 'balance' => '0.00']);

        Transaction::create([
            'user_id' => $user->id,
            'wallet_id' => $wallet->id,
            'type' => 'deposit',
            'status' => 'pending',
            'amount' => '100.00',
            'currency' => 'MYR',
            'order_id' => 'DP-DUPLICATE',
        ]);

        expect(fn () => Transaction::create([
            'user_id' => $user->id,
            'wallet_id' => $wallet->id,
            'type' => 'deposit',
            'status' => 'pending',
            'amount' => '100.00',
            'currency' => 'MYR',
            'order_id' => 'DP-DUPLICATE',
        ]))->toThrow(QueryException::class);
    });
});
