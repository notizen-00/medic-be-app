<?php

namespace App\Http\Controllers\Api\Patient;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\BalanceService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class BalanceController extends Controller
{
    protected BalanceService $balanceService;

    public function __construct(BalanceService $balanceService)
    {
        $this->balanceService = $balanceService;
    }

    /**
     * Menampilkan saldo dan ringkasan user
     */
    public function show()
    {
        $user = Auth::user();
        $summary = $this->balanceService->getBalanceSummary($user);

        return response()->json([
            'success' => true,
            'data' => $summary,
        ]);
    }

    /**
     * Menampilkan history transaksi user
     */
    public function history(Request $request)
    {
        $user = Auth::user();
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
     * Topup saldo user (mengembalikan informasi untuk diproses lebih lanjut)
     */
    public function topup(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'amount' => 'required|numeric|min:10000',
            'payment_method' => 'required|in:midtrans',
        ]);

        $amount = (float) $validated['amount'];

        // Pastikan saldo user ada
        $balance = $this->balanceService->getOrCreateBalance($user);

        // Generate reference number untuk topup
        $referenceNumber = \App\Models\BalanceTransaction::generateReferenceNumber('topup');

        // Buat transaksi pending
        $transaction = \App\Models\BalanceTransaction::create([
            'user_id' => $user->id,
            'balance_id' => $balance->id,
            'transaction_uuid' => \App\Models\BalanceTransaction::generateTransactionUuid(),
            'type' => 'topup',
            'status' => 'pending',
            'amount' => $amount,
            'balance_before' => $balance->balance,
            'balance_after' => $balance->balance + $amount,
            'reference_number' => $referenceNumber,
            'meta' => [
                'payment_method' => $validated['payment_method'],
                'description' => 'Topup saldo',
            ],
            'description' => 'Topup saldo',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Topup berhasil dibuat. Silakan selesaikan pembayaran.',
            'data' => [
                'transaction_id' => $transaction->id,
                'transaction_uuid' => $transaction->transaction_uuid,
                'reference_number' => $transaction->reference_number,
                'amount' => $amount,
                'payment_url' => null, // Akan diisi oleh Midtrans callback
            ],
        ]);
    }

    /**
     * Konfirmasi topup berhasil (dipanggil oleh Midtrans callback)
     */
    public function confirmTopup(Request $request)
    {
        throw ValidationException::withMessages([
            'topup' => ['Top-up tidak dapat dikonfirmasi oleh aplikasi pasien. Saldo hanya boleh dikreditkan oleh callback payment gateway yang terverifikasi.'],
        ]);
    }
}
