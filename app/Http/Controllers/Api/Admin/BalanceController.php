<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserBalance;
use App\Models\BalanceTransaction;
use Illuminate\Http\Request;
use App\Services\BalanceService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class BalanceController extends Controller
{
    protected BalanceService $balanceService;

    public function __construct(BalanceService $balanceService)
    {
        $this->balanceService = $balanceService;
    }

    /**
     * Menampilkan semua saldo user
     */
    public function index(Request $request)
    {
        $query = UserBalance::with(['user']);

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Search by user name or email
        if ($request->has('search')) {
            $search = $request->search;
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $balances = $query->latest()->paginate($request->input('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $balances,
        ]);
    }

    /**
     * Menampilkan detail saldo user spesifik
     */
    public function show(User $user)
    {
        $balance = $this->balanceService->getOrCreateBalance($user);
        $summary = $this->balanceService->getBalanceSummary($user);

        return response()->json([
            'success' => true,
            'data' => [
                'user' => $user,
                'balance' => $balance,
                'summary' => $summary,
            ],
        ]);
    }

    /**
     * History transaksi user spesifik
     */
    public function history(User $user, Request $request)
    {
        $perPage = $request->input('per_page', 20);
        $type = $request->input('type');
        $status = $request->input('status');

        $transactions = $this->balanceService->getTransactionHistory(
            $user,
            (int) $perPage,
            $type,
            $status
        );

        return response()->json([
            'success' => true,
            'data' => $transactions,
        ]);
    }

    /**
     * Refund saldo untuk user
     */
    public function refund(User $user, Request $request)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:1',
            'reference_type' => 'nullable|string',
            'reference_id' => 'nullable|integer',
            'description' => 'nullable|string|max:500',
        ]);

        $balance = $this->balanceService->getOrCreateBalance($user);

        $transaction = $this->balanceService->refund(
            $balance,
            (float) $validated['amount'],
            [
                'reference_type' => $validated['reference_type'] ?? null,
                'reference_id' => $validated['reference_id'] ?? null,
                'description' => $validated['description'] ?? 'Refund oleh admin',
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Refund berhasil dilakukan',
            'data' => [
                'transaction' => $transaction,
                'new_balance' => $balance->balance,
            ],
        ]);
    }

    /**
     * Adjustment saldo oleh admin
     */
    public function adjust(User $user, Request $request)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric',
            'description' => 'required|string|max:500',
        ]);

        $balance = $this->balanceService->getOrCreateBalance($user);
        $adminUser = Auth::user();

        $transaction = $this->balanceService->adjust(
            $balance,
            (float) $validated['amount'],
            $adminUser,
            [
                'description' => $validated['description'],
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Adjustment berhasil dilakukan',
            'data' => [
                'transaction' => $transaction,
                'new_balance' => $balance->balance,
            ],
        ]);
    }

    /**
     * Transaksi semua user (untuk audit admin)
     */
    public function allTransactions(Request $request)
    {
        $query = BalanceTransaction::with(['user', 'adminUser', 'balance']);

        // Filter by type
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by user
        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        // Filter by date range
        if ($request->has('from_date')) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }

        if ($request->has('to_date')) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }

        $transactions = $query->latest()->paginate($request->input('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $transactions,
        ]);
    }
}
