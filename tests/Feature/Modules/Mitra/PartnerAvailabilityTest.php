<?php

use App\Models\PartnerProfile;
use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\patchJson;

beforeEach(function () {
    $this->mitra = User::factory()->create([
        'role' => 'mitra',
        'email' => 'mitra@example.com',
    ]);

    PartnerProfile::create([
        'user_id' => $this->mitra->id,
        'profession' => 'doctor',
        'is_available' => false,
    ]);
});

test('mitra dapat mengaktifkan ketersediaan (is_available = true)', function () {
    actingAs($this->mitra, 'sanctum');

    $response = patchJson('/api/mitra/profile/availability', [
        'is_available' => true,
    ], apiHeaders());

    $response
        ->assertOk()
        ->assertJsonPath('data.is_available', true)
        ->assertJsonFragment(['message' => 'Status ketersediaan mitra berhasil diubah menjadi Aktif.']);

    expect($this->mitra->partnerProfile->refresh()->is_available)->toBeTrue();
});

test('mitra dapat menonaktifkan ketersediaan (is_available = false)', function () {
    $this->mitra->partnerProfile->update(['is_available' => true]);

    actingAs($this->mitra, 'sanctum');

    $response = patchJson('/api/mitra/profile/availability', [
        'is_available' => false,
    ], apiHeaders());

    $response
        ->assertOk()
        ->assertJsonPath('data.is_available', false)
        ->assertJsonFragment(['message' => 'Status ketersediaan mitra berhasil diubah menjadi Tidak Aktif.']);

    expect($this->mitra->partnerProfile->refresh()->is_available)->toBeFalse();
});

test('validasi gagal jika is_available tidak dikirim', function () {
    actingAs($this->mitra, 'sanctum');

    $response = patchJson('/api/mitra/profile/availability', [], apiHeaders());

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['is_available']);
});

test('unauthenticated user tidak dapat mengakses endpoint availability', function () {
    $response = patchJson('/api/mitra/profile/availability', [
        'is_available' => true,
    ], apiHeaders());

    $response->assertUnauthorized();
});

test('user bukan mitra tidak dapat mengubah availability', function () {
    $patient = User::factory()->create(['role' => 'pasien']);

    actingAs($patient, 'sanctum');

    $response = patchJson('/api/mitra/profile/availability', [
        'is_available' => true,
    ], apiHeaders());

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['user']);
});

