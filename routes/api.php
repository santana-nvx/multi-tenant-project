<?php

use App\Http\Controllers\Auth\PlayerAuthController;
use App\Http\Controllers\BankController;
use App\Http\Controllers\DepositCallbackController;
use App\Http\Controllers\DepositController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\WalletController;
use App\Http\Controllers\WithdrawalCallbackController;
use App\Http\Controllers\WithdrawalController;
use Illuminate\Support\Facades\Route;

// Player authentication routes
Route::post('/login', [PlayerAuthController::class, 'login']);
Route::post('/deposits/callback', DepositCallbackController::class);
Route::post('/withdrawals/callback', WithdrawalCallbackController::class);

// auth api is used for player authentication, so we use the 'auth:api' middleware to protect the /me route. This ensures that only authenticated players can access their own information.
Route::middleware('auth:api')->group(function () {
    Route::get('/me', [PlayerAuthController::class, 'me']);

    Route::post('/deposits', [DepositController::class, 'store']);
    Route::get('/deposits/banks', [BankController::class, 'deposit']);

    Route::post('/withdrawals', [WithdrawalController::class, 'store']);
    Route::get('/withdrawals/banks', [BankController::class, 'withdrawal']);

    Route::get('/wallet', [WalletController::class, 'show']);
    Route::get('/transactions', [TransactionController::class, 'index']);
});
