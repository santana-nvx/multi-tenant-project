<?php

namespace App\Http\Controllers;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\Transaction;
use App\Services\Gateway\Contracts\PaymentGatewayInterface;

class AdminDashboardController extends Controller
{
    public function index(PaymentGatewayInterface $gateway)
    {
        $auth = $gateway->auth();
        $balance = $auth->succeeded() ? $gateway->balance($auth) : null;

        return response()->json([
            'merchant_balance' => $balance,
            'stats' => [
                'total_deposits' => Transaction::where('type', TransactionType::Deposit)->where('status', TransactionStatus::Success)->sum('amount'),
                'total_withdrawals' => Transaction::where('type', TransactionType::Withdrawal)->where('status', TransactionStatus::Success)->sum('amount'),
                'pending_count' => Transaction::whereIn('status', [TransactionStatus::Pending, TransactionStatus::Processing])->count(),
            ],
        ]);
    }
}
