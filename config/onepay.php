<?php

/*
 * Endpoint paths for the OnePay gateway's API (relative to each
 * GatewaySetting's base_url). Centralized here so SandboxPaymentGateway
 * never has a path typed twice, and a path change is a one-line edit
 * instead of a code search.
 */

return [
    'endpoints' => [
        'auth' => '/merchant/auth',
        'currency' => '/merchant/currency',
        'deposit_banks' => '/wallet/bank_list',
        'withdraw_banks' => '/wallet/withdraw_bank_list',
        'create_deposit' => '/merchant/generate_orders',
        'create_withdrawal' => '/merchant/withdraw_orders',
        'check_deposit_status' => '/merchant/check_status',
        'check_withdrawal_status' => '/merchant/check_withdraw_status',
        'balance' => '/wallet/get_balance',
    ],
];