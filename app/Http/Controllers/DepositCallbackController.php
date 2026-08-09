<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessDepositCallback;
use App\Services\DepositCallbackService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class DepositCallbackController extends Controller
{
    public function __construct(private DepositCallbackService $callbacks) {}

    public function __invoke(Request $request): JsonResponse
    {
        // try {
        // $this->callbacks->handle($request->all());

        // } catch (RuntimeException $e) {
        //     return response()->json(['message' => $e->getMessage()], 422);
        // }

        ProcessDepositCallback::dispatch($request->all());

        return response()->json(['status' => true]);
    }
}
