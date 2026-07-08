<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'service_booking_id',
    'partner_user_id',
    'latitude',
    'longitude',
    'accuracy_meters',
    'heading',
    'speed_mps',
    'recorded_at',
])]
class ServiceBookingPartnerLocation extends Model
{
    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'accuracy_meters' => 'decimal:2',
            'heading' => 'decimal:2',
            'speed_mps' => 'decimal:2',
            'recorded_at' => 'datetime',
        ];
    }

    public function serviceBooking(): BelongsTo
    {
        return $this->belongsTo(ServiceBooking::class);
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'partner_user_id');
    }
}
