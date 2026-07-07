<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'service_category_id',
    'service_code',
    'name',
    'slug',
    'service_type',
    'service_mode',
    'category',
    'description',
    'base_price',
    'duration_minutes',
    'requires_address',
    'requires_schedule',
    'requires_matchmaking',
    'sort_order',
    'is_active',
    'is_homecare',
])]
class Service extends Model
{
    protected function casts(): array
    {
        return [
            'base_price' => 'decimal:2',
            'requires_address' => 'boolean',
            'requires_schedule' => 'boolean',
            'requires_matchmaking' => 'boolean',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
            'is_homecare' => 'boolean',
        ];
    }

    public function serviceCategory(): BelongsTo
    {
        return $this->belongsTo(ServiceCategory::class);
    }

    public function partnerServices(): HasMany
    {
        return $this->hasMany(PartnerService::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(ServiceBooking::class);
    }
}
