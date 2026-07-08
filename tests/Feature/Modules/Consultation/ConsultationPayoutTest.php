<?php

use App\Models\Consultation;
use App\Models\PartnerProfile;
use App\Models\Payment;
use App\Models\User;
use App\Models\UserBalance;
use Laravel\Sanctum\Sanctum;

it('credits partner wallet when mitra completes a paid consultation', function () {
    $patient = User::factory()->create(['role' => 'pasien']);
    $partner = User::factory()->create(['role' => 'mitra']);

    PartnerProfile::create([
        'user_id' => $partner->id,
        'profession' => 'dokter',
        'verification_status' => 'verified',
        'consultation_fee' => 125000,
    ]);

    $consultation = Consultation::create([
        'consultation_code' => 'KONS-PAYOUT-001',
        'patient_user_id' => $patient->id,
        'partner_user_id' => $partner->id,
        'service_type' => 'chat',
        'status' => 'ongoing',
        'consultation_fee' => 125000,
    ]);

    Payment::create([
        'consultation_id' => $consultation->id,
        'patient_user_id' => $patient->id,
        'payment_code' => 'PAY-KONS-PAYOUT-001',
        'status' => 'paid',
        'amount' => 125000,
        'paid_at' => now(),
    ]);

    Sanctum::actingAs($partner);

    $this->patchJson("/api/mitra/consultations/{$consultation->id}/status", [
        'status' => 'completed',
        'diagnosis' => 'Observasi selesai.',
    ])->assertOk()
        ->assertJsonPath('data.status', 'completed')
        ->assertJsonPath('data.partner_balance_transaction.amount', '125000.00');

    $this->assertDatabaseHas('user_balances', [
        'user_id' => $partner->id,
        'balance' => 125000,
    ]);

    expect(Consultation::find($consultation->id)->partner_balance_transaction_id)->not->toBeNull();
});

it('does not credit partner wallet twice when consultation completion is submitted repeatedly', function () {
    $patient = User::factory()->create(['role' => 'pasien']);
    $partner = User::factory()->create(['role' => 'mitra']);

    PartnerProfile::create([
        'user_id' => $partner->id,
        'profession' => 'dokter',
        'verification_status' => 'verified',
        'consultation_fee' => 175000,
    ]);

    $consultation = Consultation::create([
        'consultation_code' => 'KONS-PAYOUT-002',
        'patient_user_id' => $patient->id,
        'partner_user_id' => $partner->id,
        'service_type' => 'chat',
        'status' => 'ongoing',
        'consultation_fee' => 175000,
    ]);

    Payment::create([
        'consultation_id' => $consultation->id,
        'patient_user_id' => $patient->id,
        'payment_code' => 'PAY-KONS-PAYOUT-002',
        'status' => 'paid',
        'amount' => 175000,
        'paid_at' => now(),
    ]);

    Sanctum::actingAs($partner);

    $this->patchJson("/api/mitra/consultations/{$consultation->id}/status", [
        'status' => 'completed',
    ])->assertOk();

    $firstTransactionId = Consultation::find($consultation->id)->partner_balance_transaction_id;

    $this->patchJson("/api/mitra/consultations/{$consultation->id}/status", [
        'status' => 'completed',
    ])->assertOk();

    expect(UserBalance::where('user_id', $partner->id)->value('balance'))->toBe('175000.00')
        ->and(Consultation::find($consultation->id)->partner_balance_transaction_id)->toBe($firstTransactionId);
});
