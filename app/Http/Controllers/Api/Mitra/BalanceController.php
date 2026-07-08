<?php

namespace App\Http\Controllers\Api\Mitra;

use App\Http\Controllers\Controller;
use App\Services\BalanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BalanceController extends Controller
{
    public function __construct(
        private readonly BalanceService $balanceService
    ) {
    }

    public function show()
    {
        $user = Auth::user();

        return response()->json([
            'success' => true,
            'data' => [
                'balance' => $this->balanceService->getOrCreateBalance($user),
                'summary' => $this->balanceService->getBalanceSummary($user),
            ],
        ]);
    }

    public function history(Request $request)
    {
        $validated = $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'type' => ['nullable', 'string'],
            'status' => ['nullable', 'string'],
        ]);

        $transactions = $this->balanceService->getTransactionHistory(
            Auth::user(),
            (int) ($validated['per_page'] ?? 20),
            $validated['type'] ?? null,
            $validated['status'] ?? null
        );

        return response()->json([
            'success' => true,
            'data' => $transactions,
        ]);
    }
}
