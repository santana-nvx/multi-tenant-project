<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class PlayerAuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        $token = auth('api')->attempt($request->validated());

        if (! $token) {
            return response()->json(['message' => 'Invalid credentials.'], 401);
        }

        return response()->json(['access_token' => $token, 'token_type' => 'bearer']);
    }

    public function register(RegisterRequest $request): JsonResponse
    {

        $user = User::create($request->validated());

        return response()->json(['access_token' => auth('api')->login($user), 'token_type' => 'bearer']);
    }

    public function me(): JsonResponse
    {
        return response()->json(auth('api')->user());
    }
}
