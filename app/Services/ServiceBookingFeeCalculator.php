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
        $isLiveIn = ($booking['care_mode'] ?? 'visit') === 'live_in';
        $atHospital = ($booking['location_type'] ?? 'home') === 'hospital';
        $threshold = (float) $policy->transport_distance_threshold_km;
        $transportEligible = $policy->is_active
            && ! $isLiveIn
            && $distance !== null
            && $distance > $threshold;

        $transportFee = $transportEligible ? (float) $policy->transport_fee_per_visit * $visits : 0;
        $mealFee = $policy->is_active && $atHospital
            ? (float) $policy->hospital_meal_fee_per_visit * $visits
            : 0;
        $distanceOverThresholdKm = $transportEligible
            ? round(max(0, $distance - $threshold), 2)
            : 0.0;

        return [
            'transport_fee' => round($transportFee, 2),
            'meal_fee' => round($mealFee, 2),
            'transport_eligible' => $transportEligible,
            'distance_km' => $distance,
            'distance_threshold_km' => $threshold,
            'distance_over_threshold_km' => $distanceOverThresholdKm,
            'extra_fee_total' => round($transportFee + $mealFee, 2),
            'extra_fee_applied' => ($transportFee + $mealFee) > 0,
            'messages' => [
                'transport' => $transportEligible
                    ? sprintf(
                        'Lokasi berjarak %.2f km, melewati batas %.2f km. Biaya transport tambahan Rp%s dikenakan.',
                        $distance,
                        $threshold,
                        number_format($transportFee, 0, ',', '.')
                    )
                    : null,
                'meal' => $mealFee > 0
                    ? sprintf('Lokasi rumah sakit dikenakan uang makan Rp%s.', number_format($mealFee, 0, ',', '.'))
                    : null,
            ],
            'policy_snapshot' => [
                'transport_distance_threshold_km' => $threshold,
                'transport_fee_per_visit' => (float) $policy->transport_fee_per_visit,
                'hospital_meal_fee_per_visit' => (float) $policy->hospital_meal_fee_per_visit,
                'is_active' => (bool) $policy->is_active,
            ],
        ];
    }
}
