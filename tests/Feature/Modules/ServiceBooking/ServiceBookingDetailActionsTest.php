<?php

use App\Models\PartnerProfile;
use App\Models\Payment;
use App\Models\Service;
use App\Models\ServiceBooking;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('returns chat and call actions disabled with payment notifier for unpaid patient booking detail', function () {
    $patient = User::factory()->create(['role' => 'pasien']);
    $partner = User::factory()->create(['role' => 'mitra']);
    $service = Service::create([
        'service_code' => 'SVC-DETAIL-ACTIONS-PENDING',
        'name' => 'Detail Actions Pending',
        'service_type' => 'procedure',
        'base_price' => 100000,
        'is_active' => true,
    ]);
    $booking = ServiceBooking::create([
        'booking_code' => 'SVC-DETAIL-ACTIONS-001',
        'service_id' => $service->id,
        'patient_user_id' => $patient->id,
        'assigned_partner_user_id' => $partner->id,
        'status' => 'confirmed',
        'total_amount' => 100000,
        'subtotal' => 100000,
    ]);
    Payment::create([
        'service_booking_id' => $booking->id,
        'patient_user_id' => $patient->id,
        'payment_code' => 'PAY-DETAIL-ACTIONS-001',
        'status' => 'pending',
        'amount' => 100000,
    ]);

    Sanctum::actingAs($patient);

    $this->getJson("/api/patient/service-bookings/{$booking->id}")
        ->assertOk()
        ->assertJsonPath('data.detail_actions.chat.label', 'Chat')
        ->assertJsonPath('data.detail_actions.call.label', 'Call')
        ->assertJsonPath('data.detail_actions.chat.enabled', false)
        ->assertJsonPath('data.detail_actions.call.enabled', false)
        ->assertJsonPath('data.detail_actions.chat.notifier', 'Silakan selesaikan pembayaran terlebih dahulu untuk memakai fitur ini.')
        ->assertJsonPath('data.detail_actions.call.notifier', 'Silakan selesaikan pembayaran terlebih dahulu untuk memakai fitur ini.');
});

it('returns chat and call actions enabled after service booking payment is paid', function () {
    $patient = User::factory()->create(['role' => 'pasien']);
    $partner = User::factory()->create(['role' => 'mitra']);
    PartnerProfile::create([
        'user_id' => $partner->id,
        'profession' => 'perawat',
        'specialization' => 'Homecare',
        'license_number' => 'STR-DETAIL-ACTIONS-001',
        'verification_status' => 'verified',
    ]);
    $service = Service::create([
        'service_code' => 'SVC-DETAIL-ACTIONS-PAID',
        'name' => 'Detail Actions Paid',
        'service_type' => 'procedure',
        'base_price' => 100000,
        'is_active' => true,
    ]);
    $booking = ServiceBooking::create([
        'booking_code' => 'SVC-DETAIL-ACTIONS-002',
        'service_id' => $service->id,
        'patient_user_id' => $patient->id,
        'assigned_partner_user_id' => $partner->id,
        'status' => 'confirmed',
        'total_amount' => 100000,
        'subtotal' => 100000,
    ]);
    Payment::create([
        'service_booking_id' => $booking->id,
        'patient_user_id' => $patient->id,
        'payment_code' => 'PAY-DETAIL-ACTIONS-002',
        'status' => 'paid',
        'amount' => 100000,
        'paid_at' => now(),
    ]);

    Sanctum::actingAs($patient);

    $this->getJson("/api/patient/service-bookings/{$booking->id}")
        ->assertOk()
        ->assertJsonPath('data.detail_actions.chat.label', 'Chat')
        ->assertJsonPath('data.detail_actions.call.label', 'Call')
        ->assertJsonPath('data.detail_actions.chat.enabled', true)
        ->assertJsonPath('data.detail_actions.call.enabled', true)
        ->assertJsonPath('data.detail_actions.chat.notifier', null)
        ->assertJsonPath('data.detail_actions.call.notifier', null);

    Sanctum::actingAs($partner);

    $this->getJson("/api/mitra/service-bookings/{$booking->id}")
        ->assertOk()
        ->assertJsonPath('data.detail_actions.chat.label', 'Chat')
        ->assertJsonPath('data.detail_actions.call.label', 'Call')
        ->assertJsonPath('data.detail_actions.chat.enabled', true)
        ->assertJsonPath('data.detail_actions.call.enabled', true);
});
