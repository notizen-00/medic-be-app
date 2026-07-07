<?php

use App\Models\PartnerProfile;
use App\Models\PartnerService;
use App\Models\PatientAddress;
use App\Models\Service;
use App\Models\ServiceBooking;
use App\Models\User;
use App\Services\ServicePartnerSelectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('matches quick bookings to the best online partner by distance and quality', function () {
    $patient = User::factory()->create(['role' => 'pasien']);
    $nearPartner = User::factory()->create(['role' => 'mitra']);
    $betterPartner = User::factory()->create(['role' => 'mitra']);

    $address = PatientAddress::create([
        'patient_user_id' => $patient->id,
        'recipient_name' => 'Pasien Test',
        'recipient_phone' => '081234567890',
        'address' => 'Jl. Test',
        'latitude' => -8.1700000,
        'longitude' => 113.7000000,
    ]);

    $service = Service::create([
        'service_code' => 'SVC-MATCH-TEST',
        'name' => 'Homecare Test',
        'service_type' => 'perawat_homecare',
        'base_price' => 100000,
        'is_active' => true,
        'is_homecare' => true,
    ]);

    PartnerProfile::create([
        'user_id' => $nearPartner->id,
        'profession' => 'perawat',
        'latitude' => -8.1710000,
        'longitude' => 113.7010000,
        'years_of_experience' => 1,
        'is_available' => true,
        'verification_status' => 'verified',
        'verified_at' => now(),
    ]);

    PartnerProfile::create([
        'user_id' => $betterPartner->id,
        'profession' => 'perawat',
        'latitude' => -8.1800000,
        'longitude' => 113.7100000,
        'years_of_experience' => 10,
        'is_available' => true,
        'verification_status' => 'verified',
        'verified_at' => now(),
    ]);

    PartnerService::create([
        'service_id' => $service->id,
        'partner_user_id' => $nearPartner->id,
        'coverage_radius_km' => 30,
        'is_active' => true,
        'is_verified' => true,
    ]);

    PartnerService::create([
        'service_id' => $service->id,
        'partner_user_id' => $betterPartner->id,
        'coverage_radius_km' => 30,
        'is_active' => true,
        'is_verified' => true,
    ]);

    foreach (range(1, 8) as $index) {
        ServiceBooking::create([
            'booking_code' => "SVB-DONE-{$index}",
            'service_id' => $service->id,
            'patient_user_id' => $patient->id,
            'assigned_partner_user_id' => $betterPartner->id,
            'status' => 'completed',
            'total_amount' => 100000,
        ]);
    }

    $selected = app(ServicePartnerSelectionService::class)
        ->resolveBestPartnerForQuickBooking($service->fresh(), $address);

    expect($selected->partner_user_id)->toBe($betterPartner->id)
        ->and($selected->quality_score)->toBeGreaterThan(80);
});
