<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserBalance extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'user_balances';

    protected $fillable = [
        'user_id',
        'balance',
        'reserved_balance',
        'status',
    ];

    protected $casts = [
        'balance' => 'decimal:2',
        'reserved_balance' => 'decimal:2',
    ];

    // Relasi ke User
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Relasi ke transaksi saldo
    public function transactions(): HasMany
    {
        return $this->hasMany(BalanceTransaction::class);
    }

    /**
     * Menambah saldo user
     */
    public function addBalance(float $amount, array $meta = []): BalanceTransaction
    {
        return resolve(\App\Services\BalanceService::class)->credit(
            $this,
            $amount,
            $meta
        );
    }

    /**
     * Mengurangi saldo user
     */
    public function deductBalance(float $amount, array $meta = []): BalanceTransaction
    {
        return resolve(\App\Services\BalanceService::class)->debit(
            $this,
            $amount,
            $meta
        );
    }

    /**
     * Cek apakah saldo cukup
     */
    public function hasSufficientBalance(float $amount): bool
    {
        return $this->balance >= $amount;
    }

    /**
     * Format saldo untuk display
     */
    public function getFormattedBalanceAttribute(): string
    {
        return 'Rp ' . number_format($this->balance, 0, ',', '.');
    }
}
