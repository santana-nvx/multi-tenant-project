<?php

namespace App\DTOs\Gateway;

readonly class MerchantBalanceResult
{
    public function __construct(
        public bool $status,
        public ?string $balance = null,
        public ?string $currency = null,
        public ?string $message = null) {}

    public static function fromResponse(array $data)
    {
        return new self(
            status: (bool) ($data['status'] ?? false),
            balance: isset($data['balance']) ? number_format((float) $data['balance'], 2, '.', '') : null,
            currency: 'MYR',
            message: $data['message'] ?? null,
        );
    }

    public function succeeded(): bool
    {
        return $this->status && $this->balance !== null;
    }
}
