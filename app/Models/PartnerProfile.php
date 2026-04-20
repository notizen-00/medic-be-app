<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'profession',
    'pharmacy_name',
    'specialization',
    'license_number',
    'work_location',
    'years_of_experience',
    'consultation_fee',
    'is_available',
    'bio',
])]
class PartnerProfile extends Model
{
    protected function casts(): array
    {
        return [
            'consultation_fee' => 'decimal:2',
            'is_available' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
