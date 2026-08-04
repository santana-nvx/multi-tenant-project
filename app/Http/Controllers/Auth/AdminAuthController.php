<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\JsonResponse;

class AdminAuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        $token = auth('admin')->attempt($request->validated());

        if (! $token) {
            return response()->json(['message' => 'Invalid credentials.'], 401);
        }

        return response()->json(['access_token' => $token, 'token_type' => 'bearer']);
    }

    public function me(): JsonResponse
    {
        return response()->json(auth('admin')->user());
    }
}
