<?php

use App\Models\PartnerProfile;
use App\Models\Payment;
use App\Models\Service;
use App\Models\ServiceBooking;
use App\Models\User;
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
        'total_amount' => 203500,
        'subtotal' => 203500,
        'markup_amount' => 18500,
    ]);

    Payment::create([
        'service_booking_id' => $booking->id,
        'patient_user_id' => $patient->id,
        'payment_code' => 'PAY-MITRA-COMPLETE-001',
        'status' => 'paid',
        'amount' => 203500,
        'paid_at' => now(),
    ]);

    Sanctum::actingAs($partner);

    $this->getJson("/api/mitra/service-bookings/{$booking->id}")
        ->assertOk()
        ->assertJsonPath('data.total_amount', '203500.00')
        ->assertJsonPath('data.partner_payout_amount', 185000);

    $this->patchJson("/api/mitra/service-bookings/{$booking->id}/complete", [
        'summary' => 'Layanan selesai.',
    ])->assertOk()
        ->assertJsonPath('data.status', 'completed')
        ->assertJsonPath('data.partner_balance_transaction.amount', '185000.00');

    $this->assertDatabaseHas('user_balances', [
        'user_id' => $partner->id,
        'balance' => 185000,
    ]);
});
