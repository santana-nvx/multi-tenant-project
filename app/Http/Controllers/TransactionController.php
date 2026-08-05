<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class TransactionController extends Controller
{
    public function index(Request $request)
    {
        $transactions = $request->user('api')->transactions()
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return response()->json($transactions);
    }
}
