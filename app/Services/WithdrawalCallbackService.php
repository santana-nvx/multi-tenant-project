<?php

namespace App\Services;

use App\Enums\TransactionStatus;
use App\Models\Transaction;
use App\Services\Gateway\GatewayCallbackChecker;
use App\Services\Gateway\GatewaySettingsRepository;
use RuntimeException;

class WithdrawalCallbackService
{
    /**
     * Create a new class instance.
     */
    public function __construct(
        private GatewayCallbackChecker $checker,
        private GatewaySettingsRepository $settings,
        private WithdrawalService $service,
    ) {}

    public function handle(array $payload)
    {
        $settings = $this->settings->active('sandbox');

        if (! $this->checker->isRealCallback($settings, $payload)) {
            throw new RuntimeException('Invalid callback signature.');
        }

        $transaction = Transaction::where('order_id', $payload['order_id'] ?? null)->first();

        if (! $transaction) {
            throw new RuntimeException('Unknown order_id.');
        }

        // Already handled before — a repeated callback is a no-op, not an error.
        if ($transaction->processed_at !== null) {
            return;
        }

        if (($payload['order_status'] ?? null) == 'completed') {
            $transaction->update([
                'status' => TransactionStatus::Success,
                'processed_at' => now(),
                'raw_data' => ['callback' => $payload],
            ]);

        } else {
            $transaction->update([
                'status' => TransactionStatus::Failed,
                'processed_at' => now(),
                'raw_data' => ['callback' => $payload],
            ]);

            $this->service->refundWithdrawal(
                $transaction->user,
                $payload['amount'],
                $payload['remarks']);
        }

    }
}
