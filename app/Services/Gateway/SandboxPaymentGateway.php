<?php

namespace App\Services\Gateway;

use App\DTOs\Gateway\Bank;
use App\DTOs\Gateway\CurrencyLimit;
use App\DTOs\Gateway\DepositOrderResult;
use App\DTOs\Gateway\DepositRequestData;
use App\DTOs\Gateway\GatewayAuthResult;
use App\DTOs\Gateway\WithdrawalOrderResult;
use App\DTOs\Gateway\WithdrawalRequestData;
use App\Models\GatewaySetting;
use App\Services\Gateway\Contracts\PaymentGatewayInterface;
use Illuminate\Support\Facades\Http;

class SandboxPaymentGateway implements PaymentGatewayInterface
{
    /**
     * Create a new class instance.
     */
    public function __construct(private GatewaySetting $settings) {}

    public function auth(): GatewayAuthResult
    {
        $response = Http::baseUrl($this->settings->base_url)
            ->asMultipart()
            ->post(config('onepay.endpoints.auth'), [
                ['name' => 'username', 'contents' => $this->settings->username],
                ['name' => 'api_key', 'contents' => $this->settings->api_key],
            ]);

        return GatewayAuthResult::fromResponse($response->json() ?? []);
    }

    public function currencyLimits(): array
    {

        $response = Http::baseUrl($this->settings->base_url)
            ->asMultipart()
            ->post(config('onepay.endpoints.currency'), [
                ['name' => 'username', 'contents' => $this->settings->username],

            ]);

        $datas = $response->json(['rate']) ?? [];

        $limits = [];

        foreach ($datas as $data) {
            $limits[] = CurrencyLimit::fromResponse($data);
        }

        return $limits;
    }

    public function depositBanks(string $currency): array
    {
        $response = Http::baseUrl($this->settings->base_url)
            ->asMultipart()
            ->post(config('onepay.endpoints.deposit_banks'), [
                ['name' => 'username', 'contents' => $this->settings->username],
                ['name' => 'currency', 'contents' => $currency],
            ]);

        $datas = $response->json('data') ?? [];

        $banks = [];

        foreach ($datas as $data) {
            $banks[] = Bank::fromResponse($data);
        }

        return $banks;

    }

    public function createDeposit(GatewayAuthResult $auth, DepositRequestData $data): DepositOrderResult
    {
        $response = Http::baseUrl($this->settings->base_url)
            ->asMultipart()
            ->post(config('onepay.endpoints.create_deposit'), [
                ['name' => 'username', 'contents' => $this->settings->username],
                ['name' => 'auth', 'contents' => $auth->auth],
                ['name' => 'amount', 'contents' => $data->amount],
                ['name' => 'currency', 'contents' => $data->currency],
                ['name' => 'orderid', 'contents' => $data->orderId],
                ['name' => 'email', 'contents' => $data->email],
                ['name' => 'phone_number', 'contents' => $data->phoneNumber],
                ['name' => 'redirect_url', 'contents' => $data->redirectUrl],
                ['name' => 'pay_method', 'contents' => $data->payMethod],
                ['name' => 'bank_id', 'contents' => $data->bankId],
                ['name' => 'callback_url', 'contents' => $data->callbackUrl ?? ''],
            ]);

        return DepositOrderResult::fromResponse($response->json() ?? []);
    }

    public function createWithdrawal(GatewayAuthResult $auth, WithdrawalRequestData $data): WithdrawalOrderResult
    {
        $response = Http::baseUrl($this->settings->base_url)
            ->asMultipart()
            ->post(config('onepay.endpoints.create_withdrawal'), [
                ['name' => 'auth', 'contents' => $auth->auth],
                ['name' => 'amount', 'contents' => $data->amount],
                ['name' => 'currency', 'contents' => $data->currency],
                ['name' => 'orderid', 'contents' => $data->orderId],
                ['name' => 'bank_id', 'contents' => $data->bankId],
                ['name' => 'holder_name', 'contents' => $data->holderName],
                ['name' => 'account_no', 'contents' => $data->accountNo],
                ['name' => 'callback_url', 'contents' => $data->callbackUrl ?? ''],
            ]);

        return WithdrawalOrderResult::fromResponse($response->json() ?? []);
    }

    public function withdrawalBanks(string $currency): array
    {
        $response = Http::baseUrl($this->settings->base_url)
            ->asMultipart()
            ->post(config('onepay.endpoints.withdraw_banks'), [
                ['name' => 'username', 'contents' => $this->settings->username],
                ['name' => 'currency', 'contents' => $currency],
            ]);

        $datas = $response->json('data') ?? [];

        $banks = [];
        foreach ($datas as $data) {
            $banks[] = Bank::fromResponse($data);
        }

        return $banks;
    }
}
