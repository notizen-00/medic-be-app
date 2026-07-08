<?php

use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('blocks patient tokens from mitra service booking endpoints', function () {
    $patient = User::factory()->create(['role' => 'pasien']);

    Sanctum::actingAs($patient);

    $this->getJson('/api/mitra/service-bookings')
        ->assertForbidden()
        ->assertJsonPath('success', false);
});

it('blocks mitra tokens from patient service booking endpoints', function () {
    $mitra = User::factory()->create(['role' => 'mitra']);

    Sanctum::actingAs($mitra);

    $this->getJson('/api/patient/service-bookings')
        ->assertForbidden()
        ->assertJsonPath('success', false);
});
