<?php

namespace App\Http\Controllers;

use App\Http\Requests\Deposit\DepositRequest;
use App\Services\DepositService;
use RuntimeException;

class DepositController extends Controller
{
    public function __construct(private DepositService $deposit) {}

    public function store(DepositRequest $request)
    {
        try {
            $result = $this->deposit->startDeposit(
                user: $request->user('api'),
                amount: $request->validated('amount'),
                currency: $request->validated('currency'),
                payMethod: $request->validated('pay_method'),
                bankId: $request->validated('bank_id'),
                phoneNumber: $request->validated('phone_number'),
            );
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'transaction_id' => $result['transaction']->id,
            'order_id' => $result['transaction']->order_id,
            'payment_url' => $result['payment_url'],
        ], 201);
    }
}
