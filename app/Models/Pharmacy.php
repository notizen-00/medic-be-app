<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'owner_user_id',
    'is_active',
])]
class Pharmacy extends Model
{
    protected $appends = [
        'name',
        'license_number',
        'address',
        'latitude',
        'longitude',
        'opening_time',
        'closing_time',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function profile(): HasOne
    {
        return $this->hasOne(PharmacyProfile::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function getNameAttribute(): ?string
    {
        return $this->profile?->name;
    }

    public function getLicenseNumberAttribute(): ?string
    {
        return $this->profile?->license_number;
    }

    public function getAddressAttribute(): ?string
    {
        return $this->profile?->address;
    }

    public function getLatitudeAttribute(): mixed
    {
        return $this->profile?->latitude;
    }

    public function getLongitudeAttribute(): mixed
    {
        return $this->profile?->longitude;
    }

    public function getOpeningTimeAttribute(): ?string
    {
        return $this->profile?->opening_time?->format('H:i:s');
    }

    public function getClosingTimeAttribute(): ?string
    {
        return $this->profile?->closing_time?->format('H:i:s');
    }

    public function getDescriptionAttribute(): ?string
    {
        return $this->profile?->description;
    }
}
