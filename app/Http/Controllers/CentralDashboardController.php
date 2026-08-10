<?php

namespace App\Http\Controllers;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\Tenant;
use App\Models\Transaction;

class CentralDashboardController extends Controller
{
    public function index()
    {
        $tenants = Tenant::with('domains')->get();

        $totalDeposits = '0.00';
        $totalWithdrawals = '0.00';
        $recentActivity = [];

        foreach ($tenants as $tenant) {
            $domain = null;
            if ($tenant->domains->first()) {
                $domain = $tenant->domains->first()->domain;
            }

            $tenant->run(function () use ($domain, &$totalDeposits, &$totalWithdrawals, &$recentActivity) {
                $deposits = Transaction::where('type', TransactionType::Deposit)
                    ->where('status', TransactionStatus::Success)
                    ->sum('amount');

                $withdrawals = Transaction::where('type', TransactionType::Withdrawal)
                    ->where('status', TransactionStatus::Success)
                    ->sum('amount');

                $totalDeposits = bcadd($totalDeposits, (string) $deposits, 2);
                $totalWithdrawals = bcadd($totalWithdrawals, (string) $withdrawals, 2);

                $transactions = Transaction::latest()->take(5)->get();

                foreach ($transactions as $transaction) {
                    $recentActivity[] = [
                        'tenant_domain' => $domain,
                        'type' => $transaction->type,
                        'status' => $transaction->status,
                        'amount' => $transaction->amount,
                        'currency' => $transaction->currency,
                        'created_at' => $transaction->created_at,
                    ];
                }
            });
        }

        usort($recentActivity, function ($a, $b) {
            return $b['created_at'] <=> $a['created_at'];
        });
        $recentActivity = array_slice($recentActivity, 0, 5);

        return response()->json([
            'tenant_count' => $tenants->count(),
            'total_deposits' => $totalDeposits,
            'total_withdrawals' => $totalWithdrawals,
            'recent_activity' => $recentActivity,
        ]);
    }
}
