<?php

namespace App\DTOs\Gateway;

readonly class Bank
{
    /**
     * Create a new class instance.
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $currency,
    ) {
        //
    }

    public static function fromResponse(array $data): self
    {
        return new self(
            id : (string) $data['id'],
            name: $data['bank_name'] ?? '',
            currency: $data['currency'] ?? '',
        );
    }
}
