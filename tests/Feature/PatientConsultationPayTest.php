<?php

use App\Models\Consultation;
use App\Models\PartnerProfile;
use App\Models\Payment;
use App\Models\User;
use App\Services\MidtransService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('creates a midtrans snap transaction without requiring payment method on consultation pay', function () {
    $patient = User::factory()->create([
        'role' => 'pasien',
    ]);

    $doctor = User::factory()->create([
        'role' => 'dokter',
    ]);

    PartnerProfile::create([
        'user_id' => $doctor->id,
        'profession' => 'dokter',
        'specialization' => 'Dokter Umum',
        'consultation_fee' => 150000,
        'is_available' => true,
    ]);

    $consultation = Consultation::create([
        'consultation_code' => 'KONS-TEST-001',
        'patient_user_id' => $patient->id,
        'partner_user_id' => $doctor->id,
        'service_type' => 'chat',
        'status' => 'pending',
        'consultation_fee' => 150000,
    ]);

    $payment = Payment::create([
        'consultation_id' => $consultation->id,
        'patient_user_id' => $patient->id,
        'payment_code' => 'PAY-KONS-TEST-001',
        'payment_method' => 'bank_transfer',
        'status' => 'pending',
        'amount' => 150000,
        'notes' => 'Initial note',
    ]);

    $midtransService = Mockery::mock(MidtransService::class);
    $midtransService->shouldReceive('getOrCreateSnapTransaction')
        ->once()
        ->withArgs(fn (Payment $boundPayment) => $boundPayment->is($payment))
        ->andReturn([
            'token' => 'snap-token-123',
            'redirect_url' => 'https://app.midtrans.com/snap/v2/vtweb/snap-token-123',
            'order_id' => $payment->payment_code,
            'gross_amount' => 150000,
            'is_reused' => false,
        ]);

    $this->app->instance(MidtransService::class, $midtransService);

    Sanctum::actingAs($patient);

    $this->patchJson("/api/patient/consultations/{$consultation->id}/pay", [
        'notes' => 'Bayar via Midtrans',
    ])
        ->assertOk()
        ->assertJsonPath('data.payment.payment_method', 'bank_transfer')
        ->assertJsonPath('data.payment.notes', 'Bayar via Midtrans')
        ->assertJsonPath('data.midtrans.token', 'snap-token-123');
});
