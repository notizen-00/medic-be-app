<?php

use App\Models\PartnerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('creates a consultation without requiring payment method', function () {
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
        'consultation_fee' => 125000,
        'is_available' => true,
    ]);

    Sanctum::actingAs($patient);

    $this->postJson('/api/patient/consultations', [
        'partner_user_id' => $doctor->id,
        'service_type' => 'chat',
        'complaint' => 'Demam sejak kemarin',
    ])
        ->assertCreated()
        ->assertJsonPath('data.payment.status', 'pending')
        ->assertJsonPath('data.payment.amount', '125000.00');
});
