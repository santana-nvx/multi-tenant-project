<?php

namespace App\Http\Controllers;

use App\Services\WithdrawalCallbackService;
use Illuminate\Http\Request;
use RuntimeException;

class WithdrawalCallbackController extends Controller
{
    public function __construct(private WithdrawalCallbackService $callbacks) {}

    public function __invoke(Request $request)
    {
        try {
            $this->callbacks->handle($request->all());
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['status' => true]);
    }
}
