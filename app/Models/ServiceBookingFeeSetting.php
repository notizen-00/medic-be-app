<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceBookingFeeSetting extends Model
{
    protected $fillable = [
        'transport_distance_threshold_km',
        'transport_fee_per_visit',
        'hospital_meal_fee_per_visit',
        'is_active',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'transport_distance_threshold_km' => 'decimal:2',
            'transport_fee_per_visit' => 'decimal:2',
            'hospital_meal_fee_per_visit' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public static function activePolicy(): self
    {
        return self::query()->where('is_active', true)->latest('id')->first()
            ?? new self([
                'transport_distance_threshold_km' => 10,
                'transport_fee_per_visit' => 0,
                'hospital_meal_fee_per_visit' => 0,
                'is_active' => true,
            ]);
    }
}
