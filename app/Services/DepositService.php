<?php

namespace App\Services;

use App\DTOs\Gateway\DepositRequestData;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Gateway\Contracts\PaymentGatewayInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use RuntimeException;

class DepositService
{
    /**
     * Create a new class instance.
     */
    public function __construct(private PaymentGatewayInterface $gateway)
    {
        //
    }

    public function startDeposit(User $user, string $amount, string $currency, string $payMethod, string $bankId, string $phoneNumber): array
    {

        // check deposit amount valid or not
        $this->checkAmountIsAllowed($amount, $currency);

        $wallet = $user->wallet;

        if (! $wallet) {
            $wallet = $user->wallet()->create(['currency' => $currency, 'balance' => '0.00']);
        }

        // create transaction log in system
        $transaction = Transaction::create([
            'user_id' => $user->id,
            'wallet_id' => $wallet->id,
            'type' => TransactionType::Deposit,
            'status' => TransactionStatus::Pending,
            'amount' => $amount,
            'currency' => $currency,
            'order_id' => 'DP-'.strtoupper(Str::ulid()),
            'payment_method' => $payMethod,
            'bank_id' => $bankId,
        ]);

        // check payment gateway authentication
        $auth = $this->gateway->auth();

        if (! $auth->succeeded()) {
            $transaction->update([
                'status' => TransactionStatus::Failed,
                'raw_data' => ['error' => $auth->message ?? 'gateway auth failed'],
            ]);

            throw new RuntimeException($auth->message ?? 'Unable to authenticate with payment gateway.');
        }

        // send order to payment gateway
        $order = $this->gateway->createDeposit($auth, new DepositRequestData(
            amount: $amount,
            currency: $currency,
            orderId: $transaction->order_id,
            email: $user->email,
            phoneNumber: $phoneNumber,
            redirectUrl: url('/wallet'),
            payMethod: $payMethod,
            bankId: $bankId,
            callbackUrl: config('gateway.deposit_callback_url').'/api/deposits/callback?tenant='.tenant('id'),
        ));

        if (! $order->succeeded()) {
            $transaction->update([
                'status' => TransactionStatus::Failed,
                'raw_data' => ['error' => $order->message ?? 'gateway rejected deposit'],
            ]);

            throw new RuntimeException($order->message ?? 'Unable to create deposit order.');
        }

        $transaction->update([
            'status' => TransactionStatus::Processing,
            'payment_gateway_id' => $order->paymentId,
        ]);

        return [
            'transaction' => $transaction,
            'payment_url' => $order->paymentUrl,
        ];

    }

    private function checkAmountIsAllowed(string $amount, string $currency): void
    {
        // $cacheKey = "gateway.currency_limits.{$currency}";

        $limits = $this->gateway->currencyLimits();

        // if ($limits === null) {
        //     $limits = $this->gateway->currencyLimits();
        //     Cache::put($cacheKey, $limits, config('gateway.currency_cache_ttl', 300));
        // }

        $limit = null;

        foreach ($limits as $item) {
            if ($item->currency === $currency) {
                $limit = $item;
                break;
            }
        }

        if (! $limit) {
            throw new RuntimeException("Currency {$currency} is not supported.");
        }

        if (bccomp($amount, $limit->minAmount, 2) < 0 || bccomp($amount, $limit->maxAmount, 2) > 0) {
            throw new RuntimeException("Amount must be between {$limit->minAmount} and {$limit->maxAmount} {$currency}.");
        }
    }
}
