<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'service_id',
    'partner_user_id',
    'custom_price',
    'coverage_radius_km',
    'is_active',
    'is_verified',
    'notes',
])]
class PartnerService extends Model
{
    protected function casts(): array
    {
        return [
            'custom_price' => 'decimal:2',
            'is_active' => 'boolean',
            'is_verified' => 'boolean',
        ];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'partner_user_id');
    }
}
