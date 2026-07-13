<?php

use App\Models\Payment;
use App\Models\Service;
use App\Models\ServiceBooking;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('lets patient cancel a service booking before payment and partner acceptance', function () {
    $patient = User::factory()->create(['role' => 'pasien']);
    $partner = User::factory()->create(['role' => 'mitra']);

    $service = Service::create([
        'service_code' => 'SVC-CANCEL-PENDING',
        'name' => 'Cancel Pending Test',
        'service_type' => 'procedure',
        'base_price' => 100000,
        'is_active' => true,
    ]);

    $booking = ServiceBooking::create([
        'booking_code' => 'SVC-CANCEL-001',
        'service_id' => $service->id,
        'patient_user_id' => $patient->id,
        'assigned_partner_user_id' => $partner->id,
        'status' => 'pending',
        'total_amount' => 100000,
        'subtotal' => 100000,
    ]);

    Payment::create([
        'service_booking_id' => $booking->id,
        'patient_user_id' => $patient->id,
        'payment_code' => 'PAY-CANCEL-001',
        'status' => 'pending',
        'amount' => 100000,
    ]);

    Sanctum::actingAs($patient);

    $this->patchJson("/api/patient/service-bookings/{$booking->id}/cancel", [
        'notes' => 'Saya ingin membatalkan pesanan.',
    ])->assertOk()
        ->assertJsonPath('data.status', 'cancelled')
        ->assertJsonPath('data.payment.status', 'expired');

    $this->assertDatabaseHas('service_bookings', [
        'id' => $booking->id,
        'status' => 'cancelled',
    ]);

    $this->assertDatabaseHas('payments', [
        'service_booking_id' => $booking->id,
        'status' => 'expired',
    ]);

    $this->assertDatabaseHas('service_booking_histories', [
        'service_booking_id' => $booking->id,
        'actor_user_id' => $patient->id,
        'title' => 'Pasien membatalkan booking',
    ]);

    $this->patchJson("/api/patient/service-bookings/{$booking->id}/pay")
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['service_booking']);
});

it('prevents patient cancellation after partner accepted the service booking', function () {
    $patient = User::factory()->create(['role' => 'pasien']);
    $partner = User::factory()->create(['role' => 'mitra']);

    $service = Service::create([
        'service_code' => 'SVC-CANCEL-ACCEPTED',
        'name' => 'Cancel Accepted Test',
        'service_type' => 'procedure',
        'base_price' => 100000,
        'is_active' => true,
    ]);

    $booking = ServiceBooking::create([
        'booking_code' => 'SVC-CANCEL-002',
        'service_id' => $service->id,
        'patient_user_id' => $patient->id,
        'assigned_partner_user_id' => $partner->id,
        'status' => 'confirmed',
        'accepted_at' => now(),
        'total_amount' => 100000,
        'subtotal' => 100000,
    ]);

    Payment::create([
        'service_booking_id' => $booking->id,
        'patient_user_id' => $patient->id,
        'payment_code' => 'PAY-CANCEL-002',
        'status' => 'pending',
        'amount' => 100000,
    ]);

    Sanctum::actingAs($patient);

    $this->patchJson("/api/patient/service-bookings/{$booking->id}/cancel")
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['status']);

    $this->assertDatabaseHas('service_bookings', [
        'id' => $booking->id,
        'status' => 'confirmed',
    ]);
});

it('prevents patient cancellation after service booking payment is paid', function () {
    $patient = User::factory()->create(['role' => 'pasien']);

    $service = Service::create([
        'service_code' => 'SVC-CANCEL-PAID',
        'name' => 'Cancel Paid Test',
        'service_type' => 'procedure',
        'base_price' => 100000,
        'is_active' => true,
    ]);

    $booking = ServiceBooking::create([
        'booking_code' => 'SVC-CANCEL-003',
        'service_id' => $service->id,
        'patient_user_id' => $patient->id,
        'status' => 'pending',
        'total_amount' => 100000,
        'subtotal' => 100000,
    ]);

    Payment::create([
        'service_booking_id' => $booking->id,
        'patient_user_id' => $patient->id,
        'payment_code' => 'PAY-CANCEL-003',
        'status' => 'paid',
        'amount' => 100000,
        'paid_at' => now(),
    ]);

    Sanctum::actingAs($patient);

    $this->patchJson("/api/patient/service-bookings/{$booking->id}/cancel")
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['payment']);

    $this->assertDatabaseHas('service_bookings', [
        'id' => $booking->id,
        'status' => 'pending',
    ]);
});
