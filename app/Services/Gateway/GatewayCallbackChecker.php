<?php

namespace App\Services\Gateway;

use App\Models\GatewaySetting;

class GatewayCallbackChecker
{
    public function isRealCallback(GatewaySetting $settings, array $payload): bool
    {
        $orderId = $payload['order_id'] ?? '';
        $actualToken = $payload['token'] ?? '';

        $expectedToken = md5($settings->api_secret.$orderId);

        return hash_equals($expectedToken, $actualToken);
    }
}
