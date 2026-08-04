<?php

use App\Http\Controllers\Auth\AdminAuthController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AdminAuthController::class, 'login']);

// auth admin is used for admin authentication, so we use the 'auth:admin' middleware to protect the /me route. This ensures that only authenticated admins can access their own information.
Route::middleware('auth:admin')->group(function () {
    Route::get('/me', [AdminAuthController::class, 'me']);
});
