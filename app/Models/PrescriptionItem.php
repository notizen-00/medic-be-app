<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'prescription_id',
    'medicine_name',
    'dosage',
    'frequency',
    'duration',
    'quantity',
    'instructions',
])]
class PrescriptionItem extends Model
{
    public function prescription(): BelongsTo
    {
        return $this->belongsTo(Prescription::class);
    }
}
