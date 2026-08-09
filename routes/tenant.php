<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

/*
|--------------------------------------------------------------------------
| Tenant Routes
|--------------------------------------------------------------------------
|
| Here you can register the tenant routes for your application.
| These routes are loaded by the TenantRouteServiceProvider.
|
| Feel free to customize them however you want. Good luck!
|
*/

// Route::middleware([
//     'web',
//     InitializeTenancyByDomain::class,
//     PreventAccessFromCentralDomains::class,
// ])->group(function () {
//     Route::get('/', function () {
//         return 'This is your multi-tenant application. The id of the current tenant is '.tenant('id');
//     });
// });

Route::middleware('tenant')->prefix('api')->group(base_path('routes/api.php'));

Route::middleware('tenant')->prefix('admin')->group(base_path('routes/admin.php'));

Route::middleware(['web', 'tenant'])->group(function () {
    Route::get('/tenant-check', function () {
        return response()->json(['tenant_id' => tenant('id')]);
    });

    Route::inertia('/api/login', 'PlayerLogin');
    Route::inertia('/api/register', 'PlayerRegister');

    Route::inertia('/admin/login', 'AdminLogin');

    Route::inertia('/admin/dashboard', 'AdminDashboard');

    Route::inertia('/wallet', 'WalletPage');

    Route::inertia('/wallet/deposit', 'DepositPage');

    Route::inertia('/wallet/withdraw', 'WithdrawPage');

    Route::inertia('/wallet/transactions', 'TransactionsPage');
});
