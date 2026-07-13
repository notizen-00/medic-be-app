<?php

use App\Models\ServiceBookingFeeSetting;
use App\Services\ServiceBookingFeeCalculator;

function feePolicy(array $overrides = []): ServiceBookingFeeSetting
{
    return new ServiceBookingFeeSetting($overrides + [
        'transport_distance_threshold_km' => 10,
        'transport_fee_per_visit' => 25000,
        'hospital_meal_fee_per_visit' => 15000,
        'is_active' => true,
    ]);
}

it('charges transport per visit above the configured distance', function () {
    $fees = app(ServiceBookingFeeCalculator::class)->calculate([
        'visit_plan' => 'recurring',
        'visit_count' => 4,
        'care_mode' => 'visit',
        'location_type' => 'home',
        'distance_km' => 10.01,
    ], feePolicy());

    expect($fees['transport_fee'])->toBe(100000.0)
        ->and($fees['meal_fee'])->toBe(0.0)
        ->and($fees['transport_eligible'])->toBeTrue()
        ->and($fees['distance_threshold_km'])->toBe(10.0)
        ->and($fees['distance_over_threshold_km'])->toBe(0.01)
        ->and($fees['extra_fee_applied'])->toBeTrue()
        ->and($fees['messages']['transport'])->toContain('melewati batas');
});

it('charges transport once for once visit above threshold', function () {
    $fees = app(ServiceBookingFeeCalculator::class)->calculate([
        'visit_plan' => 'once',
        'visit_count' => 1,
        'care_mode' => 'visit',
        'distance_km' => 20,
    ], feePolicy());

    expect($fees['transport_fee'])->toBe(25000.0)
        ->and($fees['transport_eligible'])->toBeTrue()
        ->and($fees['extra_fee_total'])->toBe(25000.0);
});

it('does not charge transport for threshold distance or live in bookings', function (array $booking) {
    $fees = app(ServiceBookingFeeCalculator::class)->calculate($booking, feePolicy());

    expect($fees['transport_fee'])->toBe(0.0);
})->with([
    'exactly threshold' => [[
        'visit_plan' => 'recurring', 'visit_count' => 4, 'care_mode' => 'visit', 'distance_km' => 10,
    ]],
    'live in above threshold' => [[
        'visit_plan' => 'recurring', 'visit_count' => 4, 'care_mode' => 'live_in', 'distance_km' => 20,
    ]],
]);

it('charges hospital meal money per visit including live in', function () {
    $fees = app(ServiceBookingFeeCalculator::class)->calculate([
        'visit_plan' => 'recurring',
        'visit_count' => 3,
        'care_mode' => 'live_in',
        'location_type' => 'hospital',
        'distance_km' => 30,
    ], feePolicy());

    expect($fees['transport_fee'])->toBe(0.0)
        ->and($fees['meal_fee'])->toBe(45000.0);
});
