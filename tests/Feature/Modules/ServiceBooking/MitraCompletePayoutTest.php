<?php

use App\Models\PartnerProfile;
use App\Models\Payment;
use App\Models\Service;
use App\Models\ServiceBooking;
use App\Models\User;
use App\Events\ServiceBookingMatched;
use Laravel\Sanctum\Sanctum;

it('credits mitra with service base payout instead of patient markup total when mitra completes booking', function () {
    $patient = User::factory()->create(['role' => 'pasien']);
    $partner = User::factory()->create(['role' => 'mitra']);

    PartnerProfile::create([
        'user_id' => $partner->id,
        'profession' => 'perawat',
        'verification_status' => 'verified',
    ]);

    $service = Service::create([
        'service_code' => 'SVC-MITRA-COMPLETE-PAYOUT',
        'name' => 'Homecare Mitra Payout Test',
        'service_type' => 'procedure',
        'base_price' => 185000,
        'is_active' => true,
    ]);

    $booking = ServiceBooking::create([
        'booking_code' => 'SVC-MITRA-COMPLETE-001',
        'service_id' => $service->id,
        'patient_user_id' => $patient->id,
        'assigned_partner_user_id' => $partner->id,
        'status' => 'on_the_way',
        'total_amount' => 228500,
        'subtotal' => 203500,
        'markup_amount' => 18500,
        'transport_fee' => 25000,
    ]);

    Payment::create([
        'service_booking_id' => $booking->id,
        'patient_user_id' => $patient->id,
        'payment_code' => 'PAY-MITRA-COMPLETE-001',
        'status' => 'paid',
        'amount' => 228500,
        'paid_at' => now(),
    ]);

    Sanctum::actingAs($partner);

    $this->getJson("/api/mitra/service-bookings/{$booking->id}")
        ->assertOk()
        ->assertJsonPath('data.total_amount', '210000.00')
        ->assertJsonPath('data.patient_total_amount', 228500)
        ->assertJsonPath('data.partner_payout_amount', 210000);

    $this->patchJson("/api/mitra/service-bookings/{$booking->id}/complete", [
        'summary' => 'Layanan selesai.',
    ])->assertOk()
        ->assertJsonPath('data.status', 'completed')
        ->assertJsonPath('data.partner_balance_transaction.amount', '210000.00');

    $this->assertDatabaseHas('user_balances', [
        'user_id' => $partner->id,
        'balance' => 210000,
    ]);
});

it('broadcasts mitra matched booking amount as base payout plus transport instead of patient markup total', function () {
    $patient = User::factory()->create(['role' => 'pasien']);
    $partner = User::factory()->create(['role' => 'mitra']);

    $service = Service::create([
        'service_code' => 'SVC-MITRA-EVENT-PAYOUT',
        'name' => 'Homecare Event Payout Test',
        'service_type' => 'procedure',
        'base_price' => 185000,
        'is_active' => true,
    ]);

    $booking = ServiceBooking::create([
        'booking_code' => 'SVC-MITRA-EVENT-001',
        'service_id' => $service->id,
        'patient_user_id' => $patient->id,
        'assigned_partner_user_id' => $partner->id,
        'status' => 'pending',
        'total_amount' => 228500,
        'subtotal' => 203500,
        'markup_amount' => 18500,
        'transport_fee' => 25000,
    ]);

    $payload = (new ServiceBookingMatched($booking))->broadcastWith();

    expect($payload['booking']['total_amount'])->toBe(210000.0)
        ->and($payload['booking']['patient_total_amount'])->toBe(228500.0)
        ->and($payload['booking']['partner_payout_amount'])->toBe(210000.0);
});
