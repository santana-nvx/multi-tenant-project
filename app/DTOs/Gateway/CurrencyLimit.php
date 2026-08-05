<?php

namespace App\DTOs\Gateway;

readonly class CurrencyLimit
{
    public function __construct(
        public string $currency,
        public string $minAmount,
        public string $maxAmount
    ) {}

    public static function fromResponse(array $data)
    {
        return new self(
            currency : $data['currency'],
            minAmount : number_format((float) $data['min'], 2, '.', ''),
            maxAmount : number_format((float) $data['max'], 2, '.', '')
        );
    }
}
