<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'profession',
    'specialization',
    'license_number',
    'work_location',
    'latitude',
    'longitude',
    'years_of_experience',
    'consultation_fee',
    'is_available',
    'bio',
    'verification_status',
    'verified_at',
    'verified_by_user_id',
    'str_photo_path',
    'ktp_photo_path',
])]
class PartnerProfile extends Model
{
    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'consultation_fee' => 'decimal:2',
            'is_available' => 'boolean',
            'verified_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
