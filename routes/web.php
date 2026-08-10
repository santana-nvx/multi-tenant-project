<?php

use App\Http\Controllers\Auth\CentralAdminAuthController;
use App\Http\Controllers\CentralDashboardController;
use App\Http\Controllers\TenantController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');
Route::inertia('/central-admin/login', 'CentralAdminLogin');
Route::inertia('/central-admin/dashboard', 'CentralAdminDashboard');
Route::inertia('/central-admin/tenant-management', 'CentralTenantManagement');

Route::post('/central-admin/login', [CentralAdminAuthController::class, 'login']);

Route::middleware(['auth:central-admin', 'role:central-admin,central-admin'])->group(function () {
    Route::get('/central-admin/me', [CentralAdminAuthController::class, 'me']);

    Route::get('/central-admin/dashboard/stats', [CentralDashboardController::class, 'index']);

    Route::get('/central-admin/tenants', [TenantController::class, 'index']);
    Route::post('/central-admin/tenants', [TenantController::class, 'store']);
    Route::get('/central-admin/tenants/{tenant}', [TenantController::class, 'show']);
    Route::put('/central-admin/tenants/{tenant}', [TenantController::class, 'update']);
    Route::patch('/central-admin/tenants/{tenant}/deactivate', [TenantController::class, 'deactivate']);
});
