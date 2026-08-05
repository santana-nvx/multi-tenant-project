<?php

namespace App\DTOs\Gateway;

readonly class DepositRequestData
{
    public function __construct(
        public string $amount,
        public string $currency,
        public string $orderId,
        public string $email,
        public string $phoneNumber,
        public string $redirectUrl,
        public string $payMethod,
        public string $bankId,
        public ?string $callbackUrl = null,
    ) {}
}
