<?php

namespace App\Services;

use App\DTOs\Gateway\WithdrawalRequestData;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\User;
use App\Services\Gateway\Contracts\PaymentGatewayInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class WithdrawalService
{
    /**
     * Create a new class instance.
     */
    public function __construct(private PaymentGatewayInterface $gateway)
    {
        //
    }

    public function startWithdrawal(User $user, string $withdrawalAmount, string $currency, string $receiverBank, string $holderName, string $accountNo)
    {

        $transaction = DB::transaction(function () use ($user, $withdrawalAmount, $currency, $receiverBank) {

            $wallet = $user->wallet()->lockForUpdate()->first();

            if (bccomp($wallet->balance, $withdrawalAmount, 2) < 0) {
                throw new RuntimeException('Insufficient Balance');
            }

            $wallet->update([
                'balance' => bcsub($wallet->balance, (string) $withdrawalAmount, 2),
            ]);

            return $user->transactions()->create([
                'wallet_id' => $wallet->id,
                'user_id' => $user->id,
                'type' => TransactionType::Withdrawal,
                'status' => TransactionStatus::Pending,
                'currency' => $currency,
                'amount' => $withdrawalAmount,
                'order_id' => 'WTH-'.strtoupper(Str::Ulid()),
                'bank_id' => $receiverBank,
            ]);
        });

        $auth = $this->gateway->auth();

        if (! $auth->succeeded()) {
            $transaction->update([
                'status' => TransactionStatus::Failed,
                'raw_data' => ['error' => $auth->message ?? 'gateway auth failed'],
            ]);

            $this->refundWithdrawal($user, $withdrawalAmount, $auth->message ?? 'gateway auth failed');

            throw new RuntimeException($auth->message ?? 'Unable to authenticate with payment gateway.');
        }

        $order = $this->gateway->createWithdrawal($auth, new WithdrawalRequestData(
            $withdrawalAmount,
            $currency,
            $transaction->order_id,
            $receiverBank,
            $holderName,
            $accountNo,
            config('gateway.deposit_callback_url').'/withdrawals/callback?tenant='.tenant('id'),
        ));

        if (! $order->succeeded()) {

            $transaction->update([
                'status' => TransactionStatus::Failed,
                'raw_data' => ['error' => $order->message ?? 'gateway rejected withdrawal'],
            ]);

            $this->refundWithdrawal($user, $withdrawalAmount, $order->message ?? 'gateway rejected withdrawwal');

            throw new RuntimeException($order->message ?? 'Unable to create withdrawal order.');
        }

        $transaction->update([
            'status' => TransactionStatus::Processing,
            'payment_gateway_id' => $order->paymentId ?? null,
        ]);

        return [
            'transaction' => $transaction,
        ];

    }

    public function refundWithdrawal(User $user, string $amount, string $message)
    {

        $wallet = $user->wallet()->lockForUpdate()->first();

        $wallet->update([
            'balance' => bcadd($wallet->balance, $amount, 2),
        ]);

    }
}
