<?php

namespace App\Services\Gateway;

use App\Models\GatewaySetting;
use RuntimeException;

class GatewaySettingsRepository
{
    public function active(string $gatewayName = 'sandbox'): GatewaySetting
    {
        return GatewaySetting::where('gateway_name', $gatewayName)
            ->where('is_active', true)
            ->first() ?? throw new RuntimeException('No active gateway settings found.');
    }
}
