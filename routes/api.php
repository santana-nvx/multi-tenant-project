<?php

use App\Http\Controllers\Auth\PlayerAuthController;
use Illuminate\Support\Facades\Route;

// Player authentication routes
Route::post('/login', [PlayerAuthController::class, 'login']);

// auth api is used for player authentication, so we use the 'auth:api' middleware to protect the /me route. This ensures that only authenticated players can access their own information.
Route::middleware('auth:api')->group(function () {
    Route::get('/me', [PlayerAuthController::class, 'me']);
});
