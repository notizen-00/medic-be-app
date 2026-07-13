<?php

use App\Models\BalanceTransaction;
use App\Models\PartnerProfile;
use App\Models\Payment;
use App\Models\Service;
use App\Models\ServiceBooking;
use App\Models\User;
use App\Services\BalanceService;
use Laravel\Sanctum\Sanctum;

it('credits an idempotent balance operation only once', function () {
    $partner = User::factory()->create(['role' => 'mitra']);
    $service = app(BalanceService::class);
    $balance = $service->getOrCreateBalance($partner);

    $first = $service->credit($balance, 100000, ['idempotency_key' => 'test:payout:1']);
    $second = $service->credit($balance->fresh(), 100000, ['idempotency_key' => 'test:payout:1']);

    expect($second->id)->toBe($first->id)
        ->and($balance->fresh()->balance)->toBe('100000.00')
        ->and(BalanceTransaction::where('idempotency_key', 'test:payout:1')->count())->toBe(1);
});

it('blocks cancellation of a paid service booking', function () {
    $patient = User::factory()->create(['role' => 'pasien']);
    $partner = User::factory()->create(['role' => 'mitra']);
    PartnerProfile::create(['user_id' => $partner->id, 'profession' => 'perawat', 'verification_status' => 'verified']);
    $service = Service::create([
        'service_code' => 'SVC-SEC-CANCEL', 'name' => 'Security Test', 'service_type' => 'procedure',
        'base_price' => 100000, 'is_active' => true,
    ]);
    $booking = ServiceBooking::create([
        'booking_code' => 'BOOK-SEC-CANCEL', 'service_id' => $service->id,
        'patient_user_id' => $patient->id, 'assigned_partner_user_id' => $partner->id,
        'status' => 'confirmed', 'total_amount' => 100000,
    ]);
    Payment::create([
        'service_booking_id' => $booking->id, 'patient_user_id' => $patient->id,
        'payment_code' => 'PAY-SEC-CANCEL', 'status' => 'paid', 'amount' => 100000, 'paid_at' => now(),
    ]);

    Sanctum::actingAs($partner);

    $this->patchJson("/api/mitra/service-bookings/{$booking->id}/status", ['status' => 'cancelled'])
        ->assertUnprocessable();

    expect($booking->fresh()->status)->toBe('confirmed');
});

it('does not let a stale expired callback overwrite a paid payment', function () {
    config(['midtrans.server_key' => 'security-test-key']);
    $patient = User::factory()->create(['role' => 'pasien']);
    $service = Service::create([
        'service_code' => 'SVC-SEC-CALLBACK', 'name' => 'Callback Security Test', 'service_type' => 'procedure',
        'base_price' => 125000, 'is_active' => true,
    ]);
    $booking = ServiceBooking::create([
        'booking_code' => 'BOOK-SEC-CALLBACK', 'service_id' => $service->id,
        'patient_user_id' => $patient->id, 'status' => 'confirmed', 'total_amount' => 125000,
    ]);
    $payment = Payment::create([
        'service_booking_id' => $booking->id, 'patient_user_id' => $patient->id,
        'payment_code' => 'PAY-SEC-CALLBACK', 'status' => 'paid', 'amount' => 125000, 'paid_at' => now(),
    ]);
    $gross = '125000.00';

    $this->postJson('/api/midtrans/callback', [
        'order_id' => $payment->payment_code,
        'status_code' => '407',
        'gross_amount' => $gross,
        'signature_key' => hash('sha512', $payment->payment_code.'407'.$gross.'security-test-key'),
        'transaction_status' => 'expire',
    ])->assertOk()->assertJsonPath('data.status', 'paid');

    expect($payment->fresh()->status)->toBe('paid')
        ->and($booking->fresh()->status)->toBe('confirmed');
});

it('rejects patient-side topup confirmation', function () {
    $patient = User::factory()->create(['role' => 'pasien']);
    Sanctum::actingAs($patient);

    $this->patchJson('/api/patient/balance/topup/confirm', [
        'transaction_uuid' => 'fake', 'status' => 'completed',
    ])->assertUnprocessable();
});
