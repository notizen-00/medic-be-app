<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'booking_code',
    'service_id',
    'patient_user_id',
    'assigned_partner_user_id',
    'patient_address_id',
    'status',
    'scheduled_at',
    'started_at',
    'completed_at',
    'total_amount',
    'notes',
])]
class ServiceBooking extends Model
{
    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'total_amount' => 'decimal:2',
        ];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'patient_user_id');
    }

    public function assignedPartner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_partner_user_id');
    }

    public function address(): BelongsTo
    {
        return $this->belongsTo(PatientAddress::class, 'patient_address_id');
    }
}
