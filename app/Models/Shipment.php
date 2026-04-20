<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'shipment_code',
    'order_id',
    'courier_user_id',
    'delivery_type',
    'status',
    'assigned_at',
    'picked_up_at',
    'delivered_at',
    'notes',
])]
class Shipment extends Model
{
    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
            'picked_up_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function courier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'courier_user_id');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(ShipmentHistory::class);
    }
}
