<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'pharmacy_id',
    'sku',
    'name',
    'type',
    'category',
    'description',
    'price',
    'admin_price',
    'cost_price',
    'stock',
    'minimum_stock_alert',
    'track_stock',
    'requires_prescription',
    'is_active',
    'image',
])]
class Product extends Model
{
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'admin_price' => 'decimal:2',
            'cost_price' => 'decimal:2',
            'track_stock' => 'boolean',
            'requires_prescription' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function pharmacy(): BelongsTo
    {
        return $this->belongsTo(Pharmacy::class, 'pharmacy_id');
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
