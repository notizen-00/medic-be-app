<?php

namespace App\Services;

use App\Models\BalanceTransaction;
use App\Models\UserBalance;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class BalanceService
{
    /**
     * Membuat saldo baru untuk user (jika belum ada)
     */
    public function getOrCreateBalance(User $user): UserBalance
    {
        return UserBalance::firstOrCreate(
            ['user_id' => $user->id],
            [
                'balance' => 0,
                'reserved_balance' => 0,
                'status' => 'active',
            ]
        );
    }

    /**
     * Topup saldo user dengan database transaction dan locking
     */
    public function credit(UserBalance $balance, float $amount, array $meta = []): BalanceTransaction
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException("Jumlah topup harus lebih besar dari 0");
        }

        if ($balance->status !== 'active') {
            throw new \RuntimeException("Tidak dapat melakukan topup pada saldo dengan status: {$balance->status}");
        }

        return DB::transaction(function () use ($balance, $amount, $meta) {
            // Lock balance untuk mencegah race condition
            $balance = UserBalance::lockForUpdate()->find($balance->id);

            if (!$balance) {
                throw new ModelNotFoundException('User balance not found');
            }

            if ($balance->status !== 'active') {
                throw new \RuntimeException("Saldo tidak aktif: {$balance->status}");
            }

            // Generate reference number
            $referenceNumber = BalanceTransaction::generateReferenceNumber('topup');
            $transactionUuid = BalanceTransaction::generateTransactionUuid();

            $balanceBefore = $balance->balance;
            $newBalance = $balanceBefore + $amount;

            // Buat transaksi
            $transaction = BalanceTransaction::create([
                'user_id' => $balance->user_id,
                'balance_id' => $balance->id,
                'transaction_uuid' => $transactionUuid,
                'type' => 'topup',
                'status' => 'completed',
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $newBalance,
                'reference_number' => $referenceNumber,
                'reference_type' => $meta['reference_type'] ?? null,
                'reference_id' => $meta['reference_id'] ?? null,
                'meta' => $meta,
                'description' => $meta['description'] ?? 'Topup saldo',
            ]);

            // Update balance
            $balance->balance = $newBalance;
            $balance->save();

            Log::info('Balance topup successful', [
                'user_id' => $balance->user_id,
                'transaction_id' => $transaction->id,
                'amount' => $amount,
                'new_balance' => $newBalance,
            ]);

            return $transaction;
        });
    }

    /**
     * Refund saldo user dengan database transaction dan locking
     */
    public function refund(UserBalance $balance, float $amount, array $meta = []): BalanceTransaction
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException("Jumlah refund harus lebih besar dari 0");
        }

        if ($balance->status !== 'active') {
            throw new \RuntimeException("Tidak dapat melakukan refund pada saldo dengan status: {$balance->status}");
        }

        return DB::transaction(function () use ($balance, $amount, $meta) {
            // Lock balance untuk mencegah race condition
            $balance = UserBalance::lockForUpdate()->find($balance->id);

            if (!$balance) {
                throw new ModelNotFoundException('User balance not found');
            }

            if ($balance->status !== 'active') {
                throw new \RuntimeException("Saldo tidak aktif: {$balance->status}");
            }

            // Generate reference number
            $referenceNumber = BalanceTransaction::generateReferenceNumber('refund');
            $transactionUuid = BalanceTransaction::generateTransactionUuid();

            $balanceBefore = $balance->balance;
            $newBalance = $balanceBefore + $amount;

            // Buat transaksi
            $transaction = BalanceTransaction::create([
                'user_id' => $balance->user_id,
                'balance_id' => $balance->id,
                'transaction_uuid' => $transactionUuid,
                'type' => 'refund',
                'status' => 'completed',
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $newBalance,
                'reference_number' => $referenceNumber,
                'reference_type' => $meta['reference_type'] ?? null,
                'reference_id' => $meta['reference_id'] ?? null,
                'meta' => $meta,
                'description' => $meta['description'] ?? 'Refund saldo',
            ]);

            // Update balance
            $balance->balance = $newBalance;
            $balance->save();

            Log::info('Balance refund successful', [
                'user_id' => $balance->user_id,
                'transaction_id' => $transaction->id,
                'amount' => $amount,
                'new_balance' => $newBalance,
            ]);

            return $transaction;
        });
    }

    /**
     * Mengurangi saldo user (untuk pembayaran)
     */
    public function debit(UserBalance $balance, float $amount, array $meta = []): BalanceTransaction
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException("Jumlah debit harus lebih besar dari 0");
        }

        if ($balance->status !== 'active') {
            throw new \RuntimeException("Tidak dapat melakukan debit pada saldo dengan status: {$balance->status}");
        }

        return DB::transaction(function () use ($balance, $amount, $meta) {
            // Lock balance untuk mencegah race condition
            $balance = UserBalance::lockForUpdate()->find($balance->id);

            if (!$balance) {
                throw new ModelNotFoundException('User balance not found');
            }

            if ($balance->status !== 'active') {
                throw new \RuntimeException("Saldo tidak aktif: {$balance->status}");
            }

            // Cek saldo tersedia
            $availableBalance = $balance->balance - $balance->reserved_balance;
            if ($availableBalance < $amount) {
                throw new \RuntimeException("Saldo tidak mencukupi. Tersedia: {$availableBalance}, diperlukan: {$amount}");
            }

            // Generate reference number
            $referenceNumber = BalanceTransaction::generateReferenceNumber('deduction');
            $transactionUuid = BalanceTransaction::generateTransactionUuid();

            $balanceBefore = $balance->balance;
            $newBalance = $balanceBefore - $amount;

            // Buat transaksi
            $transaction = BalanceTransaction::create([
                'user_id' => $balance->user_id,
                'balance_id' => $balance->id,
                'transaction_uuid' => $transactionUuid,
                'type' => 'deduction',
                'status' => 'completed',
                'amount' => -$amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $newBalance,
                'reference_number' => $referenceNumber,
                'reference_type' => $meta['reference_type'] ?? null,
                'reference_id' => $meta['reference_id'] ?? null,
                'meta' => $meta,
                'description' => $meta['description'] ?? 'Pengurangan saldo',
            ]);

            // Update balance
            $balance->balance = $newBalance;
            $balance->save();

            Log::info('Balance debit successful', [
                'user_id' => $balance->user_id,
                'transaction_id' => $transaction->id,
                'amount' => $amount,
                'new_balance' => $newBalance,
            ]);

            return $transaction;
        });
    }

    /**
     * Adjustment saldo oleh admin
     */
    public function adjust(UserBalance $balance, float $amount, ?User $adminUser, array $meta = []): BalanceTransaction
    {
        if ($balance->status !== 'active') {
            throw new \RuntimeException("Tidak dapat melakukan adjustment pada saldo dengan status: {$balance->status}");
        }

        return DB::transaction(function () use ($balance, $amount, $adminUser, $meta) {
            // Lock balance untuk mencegah race condition
            $balance = UserBalance::lockForUpdate()->find($balance->id);

            if (!$balance) {
                throw new ModelNotFoundException('User balance not found');
            }

            // Generate reference number
            $referenceNumber = BalanceTransaction::generateReferenceNumber('adjustment');
            $transactionUuid = BalanceTransaction::generateTransactionUuid();

            $balanceBefore = $balance->balance;
            $newBalance = $balanceBefore + $amount;

            // Buat transaksi
            $transaction = BalanceTransaction::create([
                'user_id' => $balance->user_id,
                'balance_id' => $balance->id,
                'transaction_uuid' => $transactionUuid,
                'type' => 'adjustment',
                'status' => 'completed',
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $newBalance,
                'reference_number' => $referenceNumber,
                'admin_user_id' => $adminUser?->id,
                'meta' => $meta,
                'description' => $meta['description'] ?? 'Adjustment saldo oleh admin',
            ]);

            // Update balance
            $balance->balance = $newBalance;
            $balance->save();

            Log::info('Balance adjustment successful', [
                'user_id' => $balance->user_id,
                'transaction_id' => $transaction->id,
                'amount' => $amount,
                'admin_user_id' => $adminUser?->id,
                'new_balance' => $newBalance,
            ]);

            return $transaction;
        });
    }

    /**
     * Reserved saldo untuk transaksi yang belum selesai
     */
    public function reserve(UserBalance $balance, float $amount): BalanceTransaction
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException("Jumlah reservation harus lebih besar dari 0");
        }

        return DB::transaction(function () use ($balance, $amount) {
            // Lock balance untuk mencegah race condition
            $balance = UserBalance::lockForUpdate()->find($balance->id);

            if (!$balance) {
                throw new ModelNotFoundException('User balance not found');
            }

            // Cek saldo tersedia
            $availableBalance = $balance->balance - $balance->reserved_balance;
            if ($availableBalance < $amount) {
                throw new \RuntimeException("Saldo tidak mencukupi untuk reservasi");
            }

            $balance->reserved_balance += $amount;
            $balance->save();

            Log::info('Balance reserved', [
                'user_id' => $balance->user_id,
                'amount' => $amount,
                'new_reserved_balance' => $balance->reserved_balance,
            ]);

            return $balance;
        });
    }

    /**
     * Release reserved saldo
     */
    public function releaseReservation(UserBalance $balance, float $amount): BalanceTransaction
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException("Jumlah release harus lebih besar dari 0");
        }

        return DB::transaction(function () use ($balance, $amount) {
            // Lock balance untuk mencegah race condition
            $balance = UserBalance::lockForUpdate()->find($balance->id);

            if (!$balance) {
                throw new ModelNotFoundException('User balance not found');
            }

            if ($balance->reserved_balance < $amount) {
                throw new \RuntimeException("Reserved balance tidak mencukupi untuk release");
            }

            $balance->reserved_balance -= $amount;
            $balance->save();

            Log::info('Balance reservation released', [
                'user_id' => $balance->user_id,
                'amount' => $amount,
                'new_reserved_balance' => $balance->reserved_balance,
            ]);

            return $balance;
        });
    }

    /**
     * Dapatkan history transaksi user
     */
    public function getTransactionHistory(User $user, int $perPage = 20, string $type = null, string $status = null)
    {
        $query = BalanceTransaction::where('user_id', $user->id);

        if ($type) {
            $query->where('type', $type);
        }

        if ($status) {
            $query->where('status', $status);
        }

        return $query->with(['balance', 'adminUser'])
            ->latest()
            ->paginate($perPage);
    }

    /**
     * Dapatkan ringkasan saldo user
     */
    public function getBalanceSummary(User $user): array
    {
        $balance = $this->getOrCreateBalance($user);

        // Hitung total topup
        $totalTopup = BalanceTransaction::where('user_id', $user->id)
            ->where('type', 'topup')
            ->where('status', 'completed')
            ->sum('amount');

        // Hitung total refund
        $totalRefund = BalanceTransaction::where('user_id', $user->id)
            ->where('type', 'refund')
            ->where('status', 'completed')
            ->sum('amount');

        // Hitung total deduction/penggunaan
        $totalDeduction = BalanceTransaction::where('user_id', $user->id)
            ->whereIn('type', ['deduction', 'payment'])
            ->where('status', 'completed')
            ->sum('amount');

        return [
            'current_balance' => (float) $balance->balance,
            'reserved_balance' => (float) $balance->reserved_balance,
            'available_balance' => (float) ($balance->balance - $balance->reserved_balance),
            'total_topup' => (float) $totalTopup,
            'total_refund' => (float) $totalRefund,
            'total_deduction' => (float) $totalDeduction,
            'status' => $balance->status,
        ];
    }
}
