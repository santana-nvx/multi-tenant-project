<?php

namespace App\Services\Gateway\Contracts;

use App\DTOs\Gateway\CurrencyLimit;
use App\DTOs\Gateway\DepositOrderResult;
use App\DTOs\Gateway\DepositRequestData;
use App\DTOs\Gateway\GatewayAuthResult;

interface PaymentGatewayInterface
{
    public function auth(): GatewayAuthResult;

    /** @return array<int, CurrencyLimit> */
    public function currencyLimits(): array;

    public function depositBanks(string $currency): array;

    public function createDeposit(GatewayAuthResult $auth, DepositRequestData $data): DepositOrderResult;
}
