<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'date_of_birth',
    'gender',
    'address',
    'blood_type',
    'emergency_contact_name',
    'emergency_contact_phone',
    'allergies',
    'medical_notes',
])]
class PatientProfile extends Model
{
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
