<?php

use App\Models\GatewaySetting;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

test('a stale pending deposit is resolved and credits the wallet when the gateway reports completed', function () {
    $domain = 'reconcile-a.test';
    [$tenant, $user] = $this->createTenantWithUser(['email' => 'player@example.com', 'password' => 'password'], $domain);

    $tenant->run(function () use ($user) {
        GatewaySetting::create([
            'gateway_name' => 'sandbox',
            'environment' => 'testing',
            'username' => 'test-merchant',
            'base_url' => 'https://fake-gateway.test',
            'api_key' => 'fake-api-key',
            'api_secret' => 'fake-api-secret',
            'is_active' => true,
        ]);

        $wallet = $user->wallet()->create(['currency' => 'MYR', 'balance' => '0.00']);

        $transaction = Transaction::create([
            'user_id' => $user->id,
            'wallet_id' => $wallet->id,
            'type' => 'deposit',
            'status' => 'processing',
            'amount' => '250.00',
            'currency' => 'MYR',
            'order_id' => 'DP-STALE-TEST-1',
        ]);

        // Bypass mass-assignment protection to genuinely backdate it —
        // created_at isn't fillable, same gotcha caught during manual testing.
        $transaction->timestamps = false;
        $transaction->created_at = now()->subMinutes(30);
        $transaction->save();
    });

    Http::fake([
        'https://fake-gateway.test/merchant/check_status' => Http::response([
            'status' => true,
            'order_status' => 'completed',
        ]),
    ]);

    $this->artisan('app:check-transaction-status')->assertExitCode(0);

    $tenant->run(function () use ($user) {
        $transaction = Transaction::where('order_id', 'DP-STALE-TEST-1')->first();

        expect($transaction->status->value)->toBe('success');
        expect($transaction->processed_at)->not->toBeNull();
        expect((string) $user->wallet->fresh()->balance)->toBe('250.00');
    });
});

test('a stale processing withdrawal is refunded when the gateway reports rejected', function () {
    $domain = 'reconcile-b.test';
    [$tenant, $user] = $this->createTenantWithUser(['email' => 'player@example.com', 'password' => 'password'], $domain);

    $tenant->run(function () use ($user) {
        GatewaySetting::create([
            'gateway_name' => 'sandbox',
            'environment' => 'testing',
            'username' => 'test-merchant',
            'base_url' => 'https://fake-gateway.test',
            'api_key' => 'fake-api-key',
            'api_secret' => 'fake-api-secret',
            'is_active' => true,
        ]);

        // Balance already reflects the hold/deduct that happened at initiation.
        $wallet = $user->wallet()->create(['currency' => 'MYR', 'balance' => '0.00']);

        $transaction = Transaction::create([
            'user_id' => $user->id,
            'wallet_id' => $wallet->id,
            'type' => 'withdrawal',
            'status' => 'processing',
            'amount' => '100.00',
            'currency' => 'MYR',
            'order_id' => 'WTH-STALE-TEST-1',
        ]);

        $transaction->timestamps = false;
        $transaction->created_at = now()->subMinutes(30);
        $transaction->save();
    });

    Http::fake([
        'https://fake-gateway.test/merchant/check_withdraw_status' => Http::response([
            'status' => true,
            'order_status' => 'rejected',
        ]),
    ]);

    $this->artisan('app:check-transaction-status')->assertExitCode(0);

    $tenant->run(function () use ($user) {
        $transaction = Transaction::where('order_id', 'WTH-STALE-TEST-1')->first();

        expect($transaction->status->value)->toBe('failed');
        expect($transaction->processed_at)->not->toBeNull();
        // Held funds released back to the wallet.
        expect((string) $user->wallet->fresh()->balance)->toBe('100.00');
    });
});

test('a recent, non-stale transaction is left untouched by reconciliation', function () {
    $domain = 'reconcile-c.test';
    [$tenant, $user] = $this->createTenantWithUser(['email' => 'player@example.com', 'password' => 'password'], $domain);

    $tenant->run(function () use ($user) {
        GatewaySetting::create([
            'gateway_name' => 'sandbox',
            'environment' => 'testing',
            'username' => 'test-merchant',
            'base_url' => 'https://fake-gateway.test',
            'api_key' => 'fake-api-key',
            'api_secret' => 'fake-api-secret',
            'is_active' => true,
        ]);

        $wallet = $user->wallet()->create(['currency' => 'MYR', 'balance' => '0.00']);

        Transaction::create([
            'user_id' => $user->id,
            'wallet_id' => $wallet->id,
            'type' => 'deposit',
            'status' => 'processing',
            'amount' => '50.00',
            'currency' => 'MYR',
            'order_id' => 'DP-RECENT-TEST-1',
        ]);
    });

    Http::fake();

    $this->artisan('app:check-transaction-status')->assertExitCode(0);

    Http::assertNothingSent();

    $tenant->run(function () {
        $transaction = Transaction::where('order_id', 'DP-RECENT-TEST-1')->first();

        expect($transaction->status->value)->toBe('processing');
        expect($transaction->processed_at)->toBeNull();
    });
});
