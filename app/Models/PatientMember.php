<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'owner_user_id',
    'name',
    'relationship',
    'date_of_birth',
    'age',
    'gender',
    'phone',
    'blood_type',
    'emergency_contact_name',
    'emergency_contact_phone',
    'allergies',
    'medical_notes',
    'address_label',
    'recipient_name',
    'recipient_phone',
    'address',
    'province',
    'city',
    'district',
    'postal_code',
    'latitude',
    'longitude',
    'is_primary',
])]
class PatientMember extends Model
{
    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'age' => 'integer',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'is_primary' => 'boolean',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function serviceBookings(): HasMany
    {
        return $this->hasMany(ServiceBooking::class);
    }

    public function toPatientAddress(): PatientAddress
    {
        return new PatientAddress([
            'patient_user_id' => $this->owner_user_id,
            'label' => $this->address_label,
            'recipient_name' => $this->recipient_name ?: $this->name,
            'recipient_phone' => $this->recipient_phone ?: $this->phone,
            'address' => $this->address,
            'province' => $this->province,
            'city' => $this->city,
            'district' => $this->district,
            'postal_code' => $this->postal_code,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'is_primary' => $this->is_primary,
        ]);
    }
}
