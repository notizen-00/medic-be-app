<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'service_booking_id',
    'actor_user_id',
    'type',
    'title',
    'description',
    'meta',
    'handled_at',
])]
class ServiceBookingHistory extends Model
{
    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'handled_at' => 'datetime',
        ];
    }

    public function serviceBooking(): BelongsTo
    {
        return $this->belongsTo(ServiceBooking::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
