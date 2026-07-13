<?php

namespace App\Services;

use App\Models\ServiceBookingFeeSetting;

class ServiceBookingFeeCalculator
{
    public function calculate(array $booking, ?ServiceBookingFeeSetting $policy = null): array
    {
        $policy ??= ServiceBookingFeeSetting::activePolicy();
        $visits = max(1, (int) ($booking['visit_count'] ?? 1));
        $distance = isset($booking['distance_km']) ? (float) $booking['distance_km'] : null;
        $isRecurring = ($booking['visit_plan'] ?? 'once') === 'recurring';
        $isLiveIn = ($booking['care_mode'] ?? 'visit') === 'live_in';
        $atHospital = ($booking['location_type'] ?? 'home') === 'hospital';
        $transportEligible = $policy->is_active
            && $isRecurring
            && ! $isLiveIn
            && $distance !== null
            && $distance > (float) $policy->transport_distance_threshold_km;

        $transportFee = $transportEligible ? (float) $policy->transport_fee_per_visit * $visits : 0;
        $mealFee = $policy->is_active && $atHospital
            ? (float) $policy->hospital_meal_fee_per_visit * $visits
            : 0;

        return [
            'transport_fee' => round($transportFee, 2),
            'meal_fee' => round($mealFee, 2),
            'transport_eligible' => $transportEligible,
            'policy_snapshot' => [
                'transport_distance_threshold_km' => (float) $policy->transport_distance_threshold_km,
                'transport_fee_per_visit' => (float) $policy->transport_fee_per_visit,
                'hospital_meal_fee_per_visit' => (float) $policy->hospital_meal_fee_per_visit,
                'is_active' => (bool) $policy->is_active,
            ],
        ];
    }
}
