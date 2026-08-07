<?php

namespace App\Http\Controllers;

use App\Services\Gateway\Contracts\PaymentGatewayInterface;
use Illuminate\Http\Request;

class BankController extends Controller
{
    public function __construct(private PaymentGatewayInterface $gateway) {}

    public function deposit(Request $request)
    {

        $banks = $this->gateway->depositBanks($request->query('currency', 'MYR'));

        return response()->json(['data' => $banks]);

    }

    public function withdrawal(Request $request)
    {
        $banks = $this->gateway->withdrawalBanks($request->query('currency', 'MYR'));

        return response()->json(['data' => $banks]);
    }
}
