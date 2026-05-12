<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ServiceMarkupSetting extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'service_id',
        'markup_type',
        'markup_value',
        'min_final_price',
        'is_active',
        'priority',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'markup_value' => 'decimal:2',
        'min_final_price' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Hitung markup amount berdasarkan base price
     */
    public function calculateMarkup(float $basePrice): float
    {
        $markup = 0;

        if ($this->markup_type === 'percentage') {
            $markup = ($basePrice * $this->markup_value) / 100;
        } else {
            $markup = $this->markup_value;
        }

        return $markup;
    }

    /**
     * Hitung final price dengan markup
     */
    public function calculateFinalPrice(float $basePrice): float
    {
        $markup = $this->calculateMarkup($basePrice);
        $finalPrice = $basePrice + $markup;

        // Pastikan memenuhi minimum final price jika ada
        if ($this->min_final_price && $finalPrice < $this->min_final_price) {
            $finalPrice = $this->min_final_price;
        }

        return $finalPrice;
    }

    /**
     * Get active markup setting untuk service tertentu
     */
    public static function getActiveSetting(int $serviceId): ?self
    {
        return self::where('service_id', $serviceId)
            ->where('is_active', true)
            ->orderByDesc('priority')
            ->first();
    }

    /**
     * Cek apakah setting ini aktif
     */
    public function isActive(): bool
    {
        return $this->is_active;
    }
}
