<?php

namespace App\DTOs\Gateway;

readonly class DepositOrderResult
{
    public function __construct(
        public bool $status,
        public ?string $paymentUrl = null,
        public ?string $paymentId = null,
        public ?string $message = null
    ) {}

    public static function fromResponse(array $data)
    {
        return new self(
            status : (bool) ($data['status'] ?? false),
            paymentUrl: $data['p_url'] ?? null,
            paymentId: $data['payment_id'] ?? null,
            message : $data['message'] ?? null,
        );
    }

    public function succeeded()
    {
        return $this->status && ! empty($this->paymentUrl) && ! empty($this->paymentId);
    }
}
