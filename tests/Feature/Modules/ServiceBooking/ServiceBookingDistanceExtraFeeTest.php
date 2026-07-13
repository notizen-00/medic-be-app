<?php

use App\Models\PartnerProfile;
use App\Models\PartnerService;
use App\Models\PatientMember;
use App\Models\Service;
use App\Models\ServiceBookingFeeSetting;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('adds transport extra fee and tells frontend when service booking distance exceeds fee threshold', function () {
    $patient = User::factory()->create(['role' => 'pasien']);
    $partner = User::factory()->create(['role' => 'mitra']);

    $patientMember = PatientMember::create([
        'owner_user_id' => $patient->id,
        'name' => 'Pasien Test',
        'relationship' => 'self',
        'recipient_name' => 'Pasien Test',
        'recipient_phone' => '081234567890',
        'address' => 'Jl. Pasien',
        'latitude' => -8.1700000,
        'longitude' => 113.7000000,
    ]);

    $service = Service::create([
        'service_code' => 'SVC-DISTANCE-FEE',
        'name' => 'Pasang Infus Jarak Jauh',
        'service_type' => 'procedure',
        'service_mode' => 'visit',
        'base_price' => 185000,
        'requires_address' => true,
        'requires_schedule' => false,
        'requires_matchmaking' => true,
        'is_active' => true,
        'is_homecare' => true,
    ]);

    PartnerProfile::create([
        'user_id' => $partner->id,
        'profession' => 'perawat',
        'latitude' => -8.3000000,
        'longitude' => 113.7000000,
        'years_of_experience' => 3,
        'is_available' => true,
        'verification_status' => 'verified',
        'verified_at' => now(),
    ]);

    PartnerService::create([
        'service_id' => $service->id,
        'partner_user_id' => $partner->id,
        'price' => 185000,
        'coverage_radius_km' => 30,
        'is_active' => true,
        'is_verified' => true,
        'is_available' => true,
    ]);

    ServiceBookingFeeSetting::create([
        'transport_distance_threshold_km' => 10,
        'transport_fee_per_visit' => 25000,
        'hospital_meal_fee_per_visit' => 15000,
        'is_active' => true,
    ]);

    Sanctum::actingAs($patient);

    $this->postJson('/api/patient/service-bookings', [
        'service_id' => $service->id,
        'patient_member_id' => $patientMember->id,
        'visit_plan' => 'once',
        'care_mode' => 'visit',
        'location_type' => 'home',
    ])->assertCreated()
        ->assertJsonPath('data.pricing.base_price', 185000)
        ->assertJsonPath('data.pricing.transport_fee', '25000.00')
        ->assertJsonPath('data.pricing.extra_fee_total', 25000)
        ->assertJsonPath('data.pricing.extra_fee_applied', true)
        ->assertJsonPath('data.pricing.extra_fees.transport.applied', true)
        ->assertJsonPath('data.pricing.extra_fees.transport.threshold_km', 10)
        ->assertJsonPath('data.pricing.total_amount', 210000);
});

