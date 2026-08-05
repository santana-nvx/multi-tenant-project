<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class WalletController extends Controller
{
    public function show(Request $request)
    {
        $wallet = $request->user('api')->wallet;

        return response()->json([
            'balance' => $wallet->balance ?? '0.00',
            'currency' => $wallet->currency ?? 'MYR',
        ]);
    }
}
