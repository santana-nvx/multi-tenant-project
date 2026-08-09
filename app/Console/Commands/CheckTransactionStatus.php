<?php

namespace App\Console\Commands;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\Tenant;
use App\Models\Transaction;
use App\Services\DepositCallbackService;
use App\Services\Gateway\Contracts\PaymentGatewayInterface;
use App\Services\Gateway\GatewaySettingsRepository;
use App\Services\WithdrawalCallbackService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:check-transaction-status')]
#[Description('Check Transaction Status If Transaction Is Pending / Processing')]
class CheckTransactionStatus extends Command
{
    /**
     * Execute the console command.
     */
    public function handle()
    {
        $status_arr = [TransactionStatus::Pending, TransactionStatus::Processing];

        $tenants = Tenant::all();

        foreach ($tenants as $tenant) {
            $tenant->run(function () use ($status_arr) {
                $settings = app(GatewaySettingsRepository::class)->active('sandbox');
                $transactions = Transaction::whereIn('status', $status_arr)
                    ->where('created_at', '<', now()->subMinutes(15))
                    ->get();

                foreach ($transactions as $transaction) {
                    if ($transaction->type == TransactionType::Deposit) {
                        $orderStatus = app(PaymentGatewayInterface::class)->checkDepositStatus($transaction->order_id);
                    } else {
                        $orderStatus = app(PaymentGatewayInterface::class)->checkWithdrawalStatus($transaction->order_id);
                    }

                    $payload = [
                        'order_id' => $transaction->order_id,
                        'order_status' => $orderStatus,
                        'token' => md5($settings->api_secret.$transaction->order_id),
                        'amount' => $transaction->amount,
                        'remarks' => 'Resolved via reconciliation',
                    ];

                    if ($transaction->type == TransactionType::Deposit) {
                        app(DepositCallbackService::class)->handle($payload);
                    } else {
                        app(WithdrawalCallbackService::class)->handle($payload);
                    }

                }
            });
        }

    }
}
