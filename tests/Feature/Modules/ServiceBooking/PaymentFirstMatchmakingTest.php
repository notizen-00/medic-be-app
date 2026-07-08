<?php

use App\Models\PartnerProfile;
use App\Models\PartnerService;
use App\Models\PatientMember;
use App\Models\Service;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('matches a service booking before payment and lets the partner accept before departure', function () {
    config(['midtrans.server_key' => 'test-server-key']);

    $patient = User::factory()->create(['role' => 'pasien']);
    $partner = User::factory()->create(['role' => 'mitra']);

    $patientMember = PatientMember::create([
        'owner_user_id' => $patient->id,
        'name' => 'Pasien Test',
        'relationship' => 'self',
        'recipient_name' => 'Pasien Test',
        'recipient_phone' => '081234567890',
        'address' => 'Jl. Test',
        'latitude' => -8.1700000,
        'longitude' => 113.7000000,
    ]);

    $service = Service::create([
        'service_code' => 'SVC-PAY-FIRST',
        'name' => 'Pasang Infus',
        'service_type' => 'procedure',
        'service_mode' => 'visit',
        'base_price' => 100000,
        'requires_address' => true,
        'requires_schedule' => false,
        'requires_matchmaking' => true,
        'is_active' => true,
        'is_homecare' => true,
    ]);

    PartnerProfile::create([
        'user_id' => $partner->id,
        'profession' => 'perawat',
        'latitude' => -8.1710000,
        'longitude' => 113.7010000,
        'years_of_experience' => 3,
        'is_available' => true,
        'verification_status' => 'verified',
        'verified_at' => now(),
    ]);

    PartnerService::create([
        'service_id' => $service->id,
        'partner_user_id' => $partner->id,
        'price' => 100000,
        'coverage_radius_km' => 30,
        'is_active' => true,
        'is_verified' => true,
        'is_available' => true,
    ]);

    Sanctum::actingAs($patient);

    $bookingResponse = $this->postJson('/api/patient/service-bookings', [
        'service_id' => $service->id,
        'patient_member_id' => $patientMember->id,
        'notes' => 'Pasien membutuhkan infus.',
    ]);

    $bookingResponse
        ->assertCreated()
        ->assertJsonPath('data.booking.assigned_partner_user_id', $partner->id)
        ->assertJsonPath('data.matchmaking_status', 'waiting_partner_acceptance');

    $bookingId = $bookingResponse->json('data.booking.id');
    $paymentCode = $bookingResponse->json('data.booking.payment.payment_code');

    Sanctum::actingAs($partner);

    $this->patchJson("/api/mitra/service-bookings/{$bookingId}/accept", [
        'notes' => 'Mitra menerima pesanan sebelum pasien bayar.',
    ])->assertOk()
        ->assertJsonPath('data.status', 'confirmed');

    $this->postJson('/api/midtrans/callback', [
        'order_id' => $paymentCode,
        'status_code' => '200',
        'gross_amount' => '100000.00',
        'signature_key' => hash('sha512', $paymentCode.'200100000.00test-server-key'),
        'transaction_status' => 'settlement',
        'payment_type' => 'bank_transfer',
        'settlement_time' => now()->format('Y-m-d H:i:s'),
    ])->assertOk();

    $this->assertDatabaseHas('payments', [
        'payment_code' => $paymentCode,
        'status' => 'paid',
    ]);

    $this->assertDatabaseHas('service_bookings', [
        'id' => $bookingId,
        'patient_member_id' => $patientMember->id,
        'patient_address_id' => null,
        'assigned_partner_user_id' => $partner->id,
        'status' => 'confirmed',
    ]);
});
