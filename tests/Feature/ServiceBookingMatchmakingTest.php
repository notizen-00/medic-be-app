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

it('uses admin service base price for non consultation service booking regardless of partner custom price', function () {
    $patient = User::factory()->create(['role' => 'pasien']);
    $expensivePartner = User::factory()->create(['role' => 'mitra']);
    $cheapPartner = User::factory()->create(['role' => 'mitra']);

    $address = PatientAddress::create([
        'patient_user_id' => $patient->id,
        'recipient_name' => 'Pasien Test',
        'recipient_phone' => '081234567890',
        'address' => 'Jl. Test',
        'latitude' => -8.1700000,
        'longitude' => 113.7000000,
    ]);

    $service = Service::create([
        'service_code' => 'SVC-ADMIN-PRICE',
        'name' => 'Pasang Infus Harga Admin',
        'service_type' => 'procedure',
        'service_mode' => 'visit',
        'base_price' => 185000,
        'requires_address' => true,
        'requires_matchmaking' => true,
        'is_active' => true,
        'is_homecare' => true,
    ]);

    foreach ([[$expensivePartner, -8.1710000, 113.7010000], [$cheapPartner, -8.1720000, 113.7020000]] as [$partner, $latitude, $longitude]) {
        PartnerProfile::create([
            'user_id' => $partner->id,
            'profession' => 'perawat',
            'latitude' => $latitude,
            'longitude' => $longitude,
            'years_of_experience' => 3,
            'is_available' => true,
            'verification_status' => 'verified',
            'verified_at' => now(),
        ]);
    }

    PartnerService::create([
        'service_id' => $service->id,
        'partner_user_id' => $expensivePartner->id,
        'price' => 500000,
        'custom_price' => 500000,
        'coverage_radius_km' => 30,
        'is_active' => true,
        'is_verified' => true,
        'is_available' => true,
    ]);

    PartnerService::create([
        'service_id' => $service->id,
        'partner_user_id' => $cheapPartner->id,
        'price' => 10000,
        'custom_price' => 10000,
        'coverage_radius_km' => 30,
        'is_active' => true,
        'is_verified' => true,
        'is_available' => true,
    ]);

    $catalog = app(ServicePartnerSelectionService::class)
        ->getServiceCatalog(['patient_address_id' => $address->id])
        ->first();

    $selected = app(ServicePartnerSelectionService::class)
        ->resolveBestPartnerForQuickBooking($service->fresh(), $address);

    expect($catalog['starting_price'])->toBe(185000.0)
        ->and($catalog['best_partner']['price'])->toBe(185000.0)
        ->and($selected->effective_price)->toBe(185000.0);
});

it('keeps partner custom price for consultation services', function () {
    $doctor = User::factory()->create(['role' => 'mitra']);

    $service = Service::create([
        'service_code' => 'SVC-CONSULT-CUSTOM',
        'name' => 'Konsultasi Dokter Custom',
        'service_type' => 'consultation',
        'service_mode' => 'chat',
        'base_price' => 50000,
        'requires_matchmaking' => true,
        'is_active' => true,
    ]);

    PartnerProfile::create([
        'user_id' => $doctor->id,
        'profession' => 'dokter',
        'years_of_experience' => 5,
        'is_available' => true,
        'verification_status' => 'verified',
        'verified_at' => now(),
    ]);

    PartnerService::create([
        'service_id' => $service->id,
        'partner_user_id' => $doctor->id,
        'price' => 125000,
        'custom_price' => 125000,
        'coverage_radius_km' => 30,
        'is_active' => true,
        'is_verified' => true,
        'is_available' => true,
    ]);

    $selected = app(ServicePartnerSelectionService::class)
        ->resolveBestPartnerForQuickBooking($service->fresh(), null);

    expect($selected->effective_price)->toBe(125000.0);
});
