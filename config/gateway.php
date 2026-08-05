<?php

return [
    'currency_cache_ttl' => env('GATEWAY_CURRENCY_CACHE_TTL', 300),
    'deposit_redirect_url' => env('GATEWAY_DEPOSIT_REDIRECT_URL'),
    'deposit_callback_url' => env('GATEWAY_DEPOSIT_CALLBACK_URL'),
];
