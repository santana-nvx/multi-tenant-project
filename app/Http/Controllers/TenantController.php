<?php

namespace App\Http\Controllers;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Http\Requests\Tenant\StoreTenantRequest;
use App\Http\Requests\Tenant\UpdateTenantRequest;
use App\Models\Tenant;
use App\Models\Transaction;
use App\Services\TenantCreationService;
use Illuminate\Http\Request;

class TenantController extends Controller
{
    public function index(Request $request)
    {
        $query = Tenant::query()->with('domains');

        if ($request->filled('search')) {
            $search = $request->query('search');

            $query->whereHas('domains', function ($domainQuery) use ($search) {
                $domainQuery->where('domain', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            if ($request->query('status') === 'active') {
                $query->where(function ($statusQuery) {
                    $statusQuery->whereNull('data->is_active')->orWhere('data->is_active', true);
                });
            } elseif ($request->query('status') === 'inactive') {
                $query->where('data->is_active', false);
            }
        }

        $tenants = $query->paginate($request->integer('per_page', 15));

        $tenantList = [];

        foreach ($tenants as $tenant) {
            $totals = $this->getTenantTotals($tenant);

            $domain = null;
            if ($tenant->domains->first()) {
                $domain = $tenant->domains->first()->domain;
            }

            $tenantList[] = [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'is_active' => $tenant->is_active,
                'domain' => $domain,
                'total_deposits' => $totals['total_deposits'],
                'total_withdrawals' => $totals['total_withdrawals'],
                'created_at' => $tenant->created_at,
            ];
        }

        return response()->json([
            'data' => $tenantList,
            'current_page' => $tenants->currentPage(),
            'last_page' => $tenants->lastPage(),
            'total' => $tenants->total(),
        ]);
    }

    public function store(StoreTenantRequest $request, TenantCreationService $creation)
    {
        $tenant = $creation->create(
            domain: $request->validated('domain'),
            tenantName: $request->validated('name'),
            userName: $request->validated('user_name'),
            userEmail: $request->validated('user_email'),
            userPassword: $request->validated('user_password'),
            adminName: $request->validated('admin_name'),
            adminEmail: $request->validated('admin_email'),
            adminPassword: $request->validated('admin_password'),
        );

        return response()->json(['id' => $tenant->id, 'name' => $tenant->name], 201);
    }

    public function show(Tenant $tenant)
    {
        $tenant->load('domains');

        $totals = $this->getTenantTotals($tenant);

        $domain = null;
        if ($tenant->domains->first()) {
            $domain = $tenant->domains->first()->domain;
        }

        return response()->json([
            'id' => $tenant->id,
            'name' => $tenant->name,
            'is_active' => $tenant->is_active,
            'domain' => $domain,
            'total_deposits' => $totals['total_deposits'],
            'total_withdrawals' => $totals['total_withdrawals'],
        ]);
    }

    public function update(UpdateTenantRequest $request, Tenant $tenant)
    {
        $tenant->update(['name' => $request->validated('name')]);

        return response()->json(['id' => $tenant->id, 'name' => $tenant->name]);
    }

    public function deactivate(Tenant $tenant)
    {
        $isCurrentlyActive = $tenant->is_active !== false;
        $tenant->update(['is_active' => ! $isCurrentlyActive]);

        return response()->json(['id' => $tenant->id, 'is_active' => $tenant->is_active]);
    }

    private function getTenantTotals(Tenant $tenant): array
    {
        return $tenant->run(function () {
            $deposits = Transaction::where('type', TransactionType::Deposit)
                ->where('status', TransactionStatus::Success)
                ->sum('amount');

            $withdrawals = Transaction::where('type', TransactionType::Withdrawal)
                ->where('status', TransactionStatus::Success)
                ->sum('amount');

            return [
                'total_deposits' => $deposits,
                'total_withdrawals' => $withdrawals,
            ];
        });
    }
}
