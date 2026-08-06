<?php

namespace App\DTOs\Gateway;

readonly class WithdrawalOrderResult
{
    /**
     * Create a new class instance.
     * This is for the callback result from payment gateway
     */
    public function __construct(
        public bool $status,
        public ?string $paymentId = null,
        public ?string $message = null,
    ) {}

    public static function fromResponse(array $array)
    {
        return new self(
            status: (bool) ($array['status'] ?? false),
            paymentId : $array['payment_id'] ?? null,
            message : $array['message'] ?? null,
        );
    }

    public function succeeded()
    {
        return $this->status && ! empty($this->paymentId);
    }
}
