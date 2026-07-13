<?php

use App\Models\PartnerProfile;
use App\Models\Payment;
use App\Models\Service;
use App\Models\ServiceBooking;
use App\Models\User;
use App\Models\UserBalance;
use Laravel\Sanctum\Sanctum;

it('credits partner wallet when patient confirms service booking completion', function () {
    $patient = User::factory()->create(['role' => 'pasien']);
    $partner = User::factory()->create(['role' => 'mitra']);

    PartnerProfile::create([
        'user_id' => $partner->id,
        'profession' => 'perawat',
        'verification_status' => 'verified',
    ]);

    $service = Service::create([
        'service_code' => 'SVC-CONFIRM-PAYOUT',
        'name' => 'Homecare Payout Test',
        'service_type' => 'procedure',
        'base_price' => 185000,
        'is_active' => true,
    ]);

    $booking = ServiceBooking::create([
        'booking_code' => 'SVC-CONFIRM-001',
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
        'payment_code' => 'PAY-CONFIRM-001',
        'status' => 'paid',
        'amount' => 243500,
        'paid_at' => now(),
    ]);

    Sanctum::actingAs($patient);

    $this->patchJson("/api/patient/service-bookings/{$booking->id}/confirm-completion", [
        'notes' => 'Layanan sudah selesai.',
    ])->assertOk()
        ->assertJsonPath('data.status', 'completed')
        ->assertJsonPath('data.partner_balance_transaction.amount', '225000.00');

    $this->assertDatabaseHas('user_balances', [
        'user_id' => $partner->id,
        'balance' => 225000,
    ]);

    $this->assertDatabaseHas('service_bookings', [
        'id' => $booking->id,
        'status' => 'completed',
    ]);

    expect(ServiceBooking::find($booking->id)->partner_balance_transaction_id)->not->toBeNull();
});

it('does not credit partner wallet twice when patient confirms completion repeatedly', function () {
    $patient = User::factory()->create(['role' => 'pasien']);
    $partner = User::factory()->create(['role' => 'mitra']);

    PartnerProfile::create([
        'user_id' => $partner->id,
        'profession' => 'perawat',
        'verification_status' => 'verified',
    ]);

    $service = Service::create([
        'service_code' => 'SVC-CONFIRM-IDEMPOTENT',
        'name' => 'Homecare Idempotent Test',
        'service_type' => 'procedure',
        'base_price' => 200000,
        'is_active' => true,
    ]);

    $booking = ServiceBooking::create([
        'booking_code' => 'SVC-CONFIRM-002',
        'service_id' => $service->id,
        'patient_user_id' => $patient->id,
        'assigned_partner_user_id' => $partner->id,
        'status' => 'on_the_way',
        'total_amount' => 200000,
        'subtotal' => 200000,
    ]);

    Payment::create([
        'service_booking_id' => $booking->id,
        'patient_user_id' => $patient->id,
        'payment_code' => 'PAY-CONFIRM-002',
        'status' => 'paid',
        'amount' => 200000,
        'paid_at' => now(),
    ]);

    Sanctum::actingAs($patient);

    $this->patchJson("/api/patient/service-bookings/{$booking->id}/confirm-completion")
        ->assertOk();

    $firstTransactionId = ServiceBooking::find($booking->id)->partner_balance_transaction_id;

    $this->patchJson("/api/patient/service-bookings/{$booking->id}/confirm-completion")
        ->assertOk();

    expect(UserBalance::where('user_id', $partner->id)->value('balance'))->toBe('200000.00')
        ->and(ServiceBooking::find($booking->id)->partner_balance_transaction_id)->toBe($firstTransactionId);
});
