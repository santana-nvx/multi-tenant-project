<?php

namespace App\Http\Controllers;

use App\Http\Requests\Withdrawal\WithdrawalRequest;
use App\Services\WithdrawalService;
use RuntimeException;

class WithdrawalController extends Controller
{
    public function __construct(private WithdrawalService $service) {}

    public function store(WithdrawalRequest $request)
    {
        try {
            $result = $this->service->startWithdrawal(
                user: $request->user('api'),
                withdrawalAmount: $request->validated('amount'),
                currency: $request->validated('currency'),
                receiverBank: $request->validated('bank_id'),
                holderName: $request->validated('holder_name'),
                accountNo: $request->validated('account_no')
            );
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'transaction_id' => $result['transaction']->id,
            'order_id' => $result['transaction']->order_id,
        ], 201);
    }
}
