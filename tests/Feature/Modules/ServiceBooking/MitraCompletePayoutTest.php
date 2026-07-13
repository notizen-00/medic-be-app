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
        'total_amount' => 243500,
        'subtotal' => 203500,
        'markup_amount' => 18500,
        'transport_fee' => 25000,
        'meal_fee' => 15000,
    ]);

    Payment::create([
        'service_booking_id' => $booking->id,
        'patient_user_id' => $patient->id,
        'payment_code' => 'PAY-MITRA-COMPLETE-001',
        'status' => 'paid',
        'amount' => 243500,
        'paid_at' => now(),
    ]);

    Sanctum::actingAs($partner);

    $this->getJson("/api/mitra/service-bookings/{$booking->id}")
        ->assertOk()
        ->assertJsonPath('data.total_amount', '225000.00')
        ->assertJsonPath('data.patient_total_amount', 243500)
        ->assertJsonPath('data.partner_payout_amount', 225000)
        ->assertJsonPath('data.partner_payout_breakdown.service_base_amount', 185000)
        ->assertJsonPath('data.partner_payout_breakdown.transport_fee', 25000)
        ->assertJsonPath('data.partner_payout_breakdown.meal_fee', 15000)
        ->assertJsonPath('data.partner_payout_breakdown.app_markup_amount', 18500)
        ->assertJsonPath('data.partner_payout_breakdown.patient_total_amount', 243500)
        ->assertJsonPath('data.partner_payout_breakdown.partner_payout_amount', 225000)
        ->assertJsonPath('data.partner_payout_breakdown.transport_fee_applied', true)
        ->assertJsonPath('data.partner_payout_breakdown.meal_fee_applied', true);

    $this->patchJson("/api/mitra/service-bookings/{$booking->id}/complete", [
        'summary' => 'Layanan selesai.',
    ])->assertOk()
        ->assertJsonPath('data.status', 'completed')
        ->assertJsonPath('data.partner_balance_transaction.amount', '225000.00');

    $this->assertDatabaseHas('user_balances', [
        'user_id' => $partner->id,
        'balance' => 225000,
    ]);
});

it('broadcasts mitra matched booking amount as base payout plus operational fees instead of patient markup total', function () {
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
        'total_amount' => 243500,
        'subtotal' => 203500,
        'markup_amount' => 18500,
        'transport_fee' => 25000,
        'meal_fee' => 15000,
    ]);

    $payload = (new ServiceBookingMatched($booking))->broadcastWith();

    expect($payload['booking']['total_amount'])->toBe(225000.0)
        ->and($payload['booking']['patient_total_amount'])->toBe(243500.0)
        ->and($payload['booking']['partner_payout_amount'])->toBe(225000.0)
        ->and($payload['booking']['partner_payout_breakdown']['service_base_amount'])->toBe(185000.0)
        ->and($payload['booking']['partner_payout_breakdown']['transport_fee'])->toBe(25000.0)
        ->and($payload['booking']['partner_payout_breakdown']['meal_fee'])->toBe(15000.0)
        ->and($payload['booking']['partner_payout_breakdown']['app_markup_amount'])->toBe(18500.0);
});
