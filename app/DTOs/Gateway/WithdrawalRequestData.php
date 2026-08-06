<?php

namespace App\DTOs\Gateway;

readonly class WithdrawalRequestData
{
    /**
     * Create a new class instance.
     */
    public function __construct(
        public string $amount,
        public string $currency,
        public string $orderId,
        public string $bankId,
        public string $holderName,
        public string $accountNo,
        public ?string $callbackUrl = null
    ) {}
}
