<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'booking_code',
    'service_id',
    'patient_user_id',
    'patient_member_id',
    'assigned_partner_user_id',
    'patient_address_id',
    'status',
    'booking_type',
    'visit_plan',
    'recurrence',
    'visit_count',
    'care_mode',
    'location_type',
    'distance_km',
    'scheduled_at',
    'schedule_start_at',
    'schedule_end_at',
    'duration_days',
    'accepted_at',
    'started_at',
    'completed_at',
    'partner_paid_at',
    'partner_balance_transaction_id',
    'total_amount',
    'notes',
    'promo_code',
    'discount_amount',
    'discount_type',
    'subtotal',
    'markup_amount',
    'transport_fee',
    'meal_fee',
    'fee_policy_snapshot',
])]
class ServiceBooking extends Model
{
    protected $appends = [
        'partner_payout_amount',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'schedule_start_at' => 'datetime',
            'schedule_end_at' => 'datetime',
            'duration_days' => 'integer',
            'visit_count' => 'integer',
            'distance_km' => 'decimal:2',
            'accepted_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'partner_paid_at' => 'datetime',
            'total_amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'markup_amount' => 'decimal:2',
            'transport_fee' => 'decimal:2',
            'meal_fee' => 'decimal:2',
            'fee_policy_snapshot' => 'array',
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

    public function patientMember(): BelongsTo
    {
        return $this->belongsTo(PatientMember::class);
    }

    public function assignedPartner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_partner_user_id');
    }

    public function partnerBalanceTransaction(): BelongsTo
    {
        return $this->belongsTo(BalanceTransaction::class, 'partner_balance_transaction_id');
    }

    public function address(): BelongsTo
    {
        return $this->belongsTo(PatientAddress::class, 'patient_address_id');
    }

    public function serviceAddress(): ?PatientAddress
    {
        if ($this->address) {
            return $this->address;
        }

        if ($this->patientMember?->address) {
            return $this->patientMember->toPatientAddress();
        }

        return null;
    }

    public function useServiceAddressRelation(): self
    {
        $address = $this->serviceAddress();

        if ($address) {
            $this->setRelation('address', $address);
        }

        return $this;
    }

    public function histories(): HasMany
    {
        return $this->hasMany(ServiceBookingHistory::class)->latest('handled_at')->latest();
    }

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }

    public function partnerLocation(): HasOne
    {
        return $this->hasOne(ServiceBookingPartnerLocation::class);
    }

    public function partnerPayoutAmount(): float
    {
        $subtotal = (float) ($this->subtotal ?? 0);
        $markupAmount = (float) ($this->markup_amount ?? 0);

        if ($subtotal > 0) {
            return max(0, $subtotal - $markupAmount);
        }

        $service = $this->relationLoaded('service')
            ? $this->service
            : Service::find($this->service_id);

        $basePrice = (float) ($service?->base_price ?? 0);
        $visitCount = max(1, (int) ($this->visit_count ?? 1));

        if ($basePrice > 0) {
            return $basePrice * $visitCount;
        }

        return max(0, (float) ($this->total_amount ?? 0));
    }

    public function getPartnerPayoutAmountAttribute(): float
    {
        return $this->partnerPayoutAmount();
    }

    /**
     * Calculate final price with markup and discount
     */
    public function calculateFinalPrice(): array
    {
        $service = $this->relationLoaded('service')
            ? $this->service
            : Service::find($this->service_id);

        $basePrice = $service?->base_price ?? $service?->price ?? 0;

        // Get active markup setting
        $markupSetting = ServiceMarkupSetting::getActiveSetting($this->service_id);
        $markupAmount = 0;

        if ($markupSetting && $markupSetting->is_active) {
            $markupAmount = $markupSetting->calculateMarkup($basePrice);
        }

        // Subtotal = base price + markup
        $subtotal = $basePrice + $markupAmount;

        // Calculate discount if promo code is used
        $discountAmount = 0;
        if ($this->promo_code) {
            $promoCode = PromoCode::where('code', $this->promo_code)
                ->where('is_active', true)
                ->first();

            if ($promoCode && $promoCode->isValid()) {
                // Check min purchase
                if ($subtotal >= $promoCode->min_purchase) {
                    $discountAmount = $promoCode->calculateDiscount($subtotal);
                }
            }
        }

        // Final price = subtotal - discount
        $finalPrice = $subtotal - $discountAmount;

        return [
            'base_price' => $basePrice,
            'markup_amount' => $markupAmount,
            'subtotal' => $subtotal,
            'discount_amount' => $discountAmount,
            'final_price' => $finalPrice,
        ];
    }

    /**
     * Validate and apply promo code
     */
    public function applyPromoCode(string $code, int $userId): array
    {
        $promoCode = PromoCode::where('code', $code)->first();

        if (!$promoCode) {
            return ['success' => false, 'message' => 'Promo code tidak ditemukan'];
        }

        if (!$promoCode->isValid()) {
            return ['success' => false, 'message' => 'Promo code tidak valid'];
        }

        if (!$promoCode->canUserUse($userId)) {
            return ['success' => false, 'message' => 'Promo code sudah digunakan'];
        }

        // Check service restriction
        if ($promoCode->service_id && $promoCode->service_id !== $this->service_id) {
            return ['success' => false, 'message' => 'Promo code tidak berlaku untuk service ini'];
        }

        $pricing = $this->calculateFinalPrice();

        if ($pricing['subtotal'] < $promoCode->min_purchase) {
            return [
                'success' => false,
                'message' => 'Minimum pembelian belum tercapai',
                'required' => $promoCode->min_purchase,
                'current' => $pricing['subtotal'],
            ];
        }

        $discountAmount = $promoCode->calculateDiscount($pricing['subtotal']);

        return [
            'success' => true,
            'promo_code' => $promoCode->code,
            'discount_type' => $promoCode->discount_type,
            'discount_value' => $promoCode->discount_value,
            'discount_amount' => $discountAmount,
            'original_price' => $pricing['subtotal'],
            'final_price' => $pricing['subtotal'] - $discountAmount,
        ];
    }
}
