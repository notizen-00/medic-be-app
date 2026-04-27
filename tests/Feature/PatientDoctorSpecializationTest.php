<?php

use App\Models\PartnerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('returns doctor specialization categories for patients', function () {
    $patient = User::factory()->create([
        'role' => 'pasien',
    ]);

    Sanctum::actingAs($patient);

    $doctorUmumA = User::factory()->create([
        'role' => 'dokter',
    ]);

    $doctorUmumB = User::factory()->create([
        'role' => 'dokter',
    ]);

    $doctorAnak = User::factory()->create([
        'role' => 'dokter',
    ]);

    $nurse = User::factory()->create([
        'role' => 'mitra',
    ]);

    PartnerProfile::create([
        'user_id' => $doctorUmumA->id,
        'profession' => 'dokter',
        'specialization' => 'Dokter Umum',
        'is_available' => true,
    ]);

    PartnerProfile::create([
        'user_id' => $doctorUmumB->id,
        'profession' => 'dokter',
        'specialization' => 'Dokter Umum',
        'is_available' => false,
    ]);

    PartnerProfile::create([
        'user_id' => $doctorAnak->id,
        'profession' => 'dokter',
        'specialization' => 'Spesialis Anak',
        'is_available' => true,
    ]);

    PartnerProfile::create([
        'user_id' => $nurse->id,
        'profession' => 'perawat',
        'specialization' => 'Perawat Homecare',
        'is_available' => true,
    ]);

    $this->getJson('/api/patient/doctors?view=specializations')
        ->assertOk()
        ->assertJsonPath('message', 'Daftar spesialisasi dokter berhasil diambil.')
        ->assertJsonCount(2, 'data')
        ->assertJsonFragment([
            'key' => 'dokter-umum',
            'name' => 'Dokter Umum',
            'specialization' => 'Dokter Umum',
            'doctor_count' => 2,
        ])
        ->assertJsonFragment([
            'key' => 'spesialis-anak',
            'name' => 'Spesialis Anak',
            'specialization' => 'Spesialis Anak',
            'doctor_count' => 1,
        ]);
});

it('filters patient doctor list by specialization', function () {
    $patient = User::factory()->create([
        'role' => 'pasien',
    ]);

    Sanctum::actingAs($patient);

    $doctorUmum = User::factory()->create([
        'name' => 'dr. Umum',
        'role' => 'dokter',
    ]);

    $doctorAnak = User::factory()->create([
        'name' => 'dr. Anak',
        'role' => 'dokter',
    ]);

    PartnerProfile::create([
        'user_id' => $doctorUmum->id,
        'profession' => 'dokter',
        'specialization' => 'Dokter Umum',
        'is_available' => true,
    ]);

    PartnerProfile::create([
        'user_id' => $doctorAnak->id,
        'profession' => 'dokter',
        'specialization' => 'Spesialis Anak',
        'is_available' => true,
    ]);

    $this->getJson('/api/patient/doctors?specialization=Dokter%20Umum')
        ->assertOk()
        ->assertJsonPath('message', 'Daftar dokter berhasil diambil.')
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'dr. Umum')
        ->assertJsonPath('data.0.partner_profile.specialization', 'Dokter Umum');
});
