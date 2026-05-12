<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PromoCode extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code',
        'name',
        'description',
        'discount_type',
        'discount_value',
        'min_purchase',
        'max_discount',
        'max_uses',
        'max_uses_per_user',
        'is_active',
        'valid_from',
        'valid_until',
        'service_id',
        'created_by',
    ];

    protected $casts = [
        'discount_value' => 'decimal:2',
        'min_purchase' => 'decimal:2',
        'max_discount' => 'decimal:2',
        'is_active' => 'boolean',
        'valid_from' => 'datetime',
        'valid_until' => 'datetime',
    ];

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Cek apakah promo code masih valid
     */
    public function isValid(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        $now = now();

        if ($this->valid_from && $now->lt($this->valid_from)) {
            return false;
        }

        if ($this->valid_until && $now->gt($this->valid_until)) {
            return false;
        }

        if ($this->max_uses && $this->usesCount() >= $this->max_uses) {
            return false;
        }

        return true;
    }

    /**
     * Hitung jumlah penggunaan promo code
     */
    public function usesCount(): int
    {
        return \App\Models\ServiceBooking::where('promo_code', $this->code)
            ->where('status', '!=', 'cancelled')
            ->count();
    }

    /**
     * Cek apakah user sudah menggunakan promo code ini
     */
    public function isUsedByUser(int $userId): bool
    {
        return \App\Models\ServiceBooking::where('promo_code', $this->code)
            ->where('patient_user_id', $userId)
            ->where('status', '!=', 'cancelled')
            ->exists();
    }

    /**
     * Hitung jumlah penggunaan per user
     */
    public function getUserUsesCount(int $userId): int
    {
        return \App\Models\ServiceBooking::where('promo_code', $this->code)
            ->where('patient_user_id', $userId)
            ->where('status', '!=', 'cancelled')
            ->count();
    }

    /**
     * Cek apakah user masih bisa menggunakan promo code ini
     */
    public function canUserUse(int $userId): bool
    {
        if (!$this->isValid()) {
            return false;
        }

        if ($this->max_uses_per_user && $this->getUserUsesCount($userId) >= $this->max_uses_per_user) {
            return false;
        }

        return true;
    }

    /**
     * Hitung discount amount berdasarkan harga
     */
    public function calculateDiscount(float $basePrice): float
    {
        $discount = 0;

        if ($this->discount_type === 'percentage') {
            $discount = ($basePrice * $this->discount_value) / 100;

            // Batasi dengan max_discount jika ada
            if ($this->max_discount && $discount > $this->max_discount) {
                $discount = $this->max_discount;
            }
        } else {
            $discount = $this->discount_value;
        }

        return min($discount, $basePrice);
    }
}
