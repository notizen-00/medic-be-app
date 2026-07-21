<?php

use App\Models\PartnerProfile;
use App\Models\Payment;
use App\Models\Service;
use App\Models\ServiceBooking;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('stores mitra handling history as status with proses penanganan meta status', function () {
    $patient = User::factory()->create(['role' => 'pasien']);
    $partner = User::factory()->create(['role' => 'mitra']);

    PartnerProfile::create([
        'user_id' => $partner->id,
        'profession' => 'perawat',
        'verification_status' => 'verified',
    ]);

    $service = Service::create([
        'service_code' => 'SVC-MITRA-HISTORY-STATUS',
        'name' => 'Homecare History Status Test',
        'service_type' => 'procedure',
        'base_price' => 100000,
        'is_active' => true,
    ]);

    $booking = ServiceBooking::create([
        'booking_code' => 'SVC-MITRA-HISTORY-001',
        'service_id' => $service->id,
        'patient_user_id' => $patient->id,
        'assigned_partner_user_id' => $partner->id,
        'status' => 'confirmed',
        'total_amount' => 100000,
        'subtotal' => 100000,
    ]);

    Payment::create([
        'service_booking_id' => $booking->id,
        'patient_user_id' => $patient->id,
        'payment_code' => 'PAY-MITRA-HISTORY-001',
        'status' => 'paid',
        'amount' => 100000,
        'paid_at' => now(),
    ]);

    Sanctum::actingAs($partner);

    $this->postJson("/api/mitra/service-bookings/{$booking->id}/histories", [
        'title' => 'Pemeriksaan awal',
        'description' => 'Mitra mulai melakukan pemeriksaan pasien.',
        'meta' => [
            'temperature' => 37.1,
            'status' => 'ignored-client-status',
        ],
    ])
        ->assertCreated()
        ->assertJsonPath('data.type', 'status')
        ->assertJsonPath('data.meta.status', 'proses penanganan')
        ->assertJsonPath('data.meta.temperature', 37.1);

    $history = $booking->histories()->firstOrFail();

    expect($history->type)->toBe('status')
        ->and($history->meta['status'])->toBe('proses penanganan')
        ->and($history->meta['temperature'])->toBe(37.1);
});
