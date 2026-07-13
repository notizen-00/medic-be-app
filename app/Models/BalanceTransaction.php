<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class BalanceTransaction extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'balance_transactions';

    protected $fillable = [
        'user_id',
        'balance_id',
        'transaction_uuid',
        'idempotency_key',
        'type',
        'status',
        'amount',
        'balance_before',
        'balance_after',
        'reference_type',
        'reference_id',
        'meta',
        'description',
        'reference_number',
        'admin_user_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'balance_before' => 'decimal:2',
        'balance_after' => 'decimal:2',
        'meta' => 'array',
    ];

    // Relasi ke User
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Relasi ke UserBalance
    public function balance(): BelongsTo
    {
        return $this->belongsTo(UserBalance::class);
    }

    // Relasi ke admin yang melakukan adjustment
    public function adminUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_user_id');
    }

    /**
     * Generate reference number unik untuk transaksi
     */
    public static function generateReferenceNumber(string $type): string
    {
        $prefix = match ($type) {
            'topup' => 'TOP',
            'refund' => 'REF',
            'deduction' => 'DED',
            'adjustment' => 'ADJ',
            'transfer' => 'TRF',
            'payment' => 'PAY',
            default => 'TXN',
        };

        $date = now()->format('Ymd');
        $random = strtoupper(substr(uniqid(), -6));

        return "{$prefix}-{$date}-{$random}";
    }

    /**
     * Generate UUID unik untuk transaksi
     */
    public static function generateTransactionUuid(): string
    {
        return (string) \Illuminate\Support\Str::uuid();
    }

    /**
     * Scope untuk transaksi masuk (topup, refund, adjustment)
     */
    public function scopeIncoming($query)
    {
        return $query->whereIn('type', ['topup', 'refund', 'adjustment']);
    }

    /**
     * Scope untuk transaksi keluar (deduction, payment, transfer)
     */
    public function scopeOutgoing($query)
    {
        return $query->whereIn('type', ['deduction', 'payment', 'transfer']);
    }

    /**
     * Scope untuk transaksi yang berhasil
     */
    public function scopeSuccess($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope untuk transaksi pending
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }
}
