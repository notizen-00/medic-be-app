<?php

use App\Events\ServiceBookingPartnerLocationUpdated;
use App\Models\PartnerProfile;
use App\Models\Service;
use App\Models\ServiceBooking;
use App\Models\ServiceBookingPartnerLocation;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;

it('lets assigned partner update realtime location and broadcasts it to tracking channel', function () {
    Event::fake([ServiceBookingPartnerLocationUpdated::class]);

    $patient = User::factory()->create(['role' => 'pasien']);
    $partner = User::factory()->create(['role' => 'mitra']);

    PartnerProfile::create([
        'user_id' => $partner->id,
        'profession' => 'perawat',
        'verification_status' => 'verified',
    ]);

    $service = Service::create([
        'service_code' => 'SVC-TRACKING-001',
        'name' => 'Homecare Tracking Test',
        'service_type' => 'procedure',
        'base_price' => 100000,
        'is_active' => true,
    ]);

    $booking = ServiceBooking::create([
        'booking_code' => 'SVC-TRACK-001',
        'service_id' => $service->id,
        'patient_user_id' => $patient->id,
        'assigned_partner_user_id' => $partner->id,
        'status' => 'on_the_way',
        'total_amount' => 100000,
    ]);

    Sanctum::actingAs($partner);

    $this->patchJson("/api/mitra/service-bookings/{$booking->id}/location", [
        'latitude' => -8.172357,
        'longitude' => 113.700302,
        'accuracy_meters' => 12.5,
        'heading' => 90,
        'speed_mps' => 4.2,
    ])->assertOk()
        ->assertJsonPath('data.location.latitude', '-8.1723570')
        ->assertJsonPath('data.location.longitude', '113.7003020');

    $this->assertDatabaseHas('service_booking_partner_locations', [
        'service_booking_id' => $booking->id,
        'partner_user_id' => $partner->id,
        'latitude' => -8.172357,
        'longitude' => 113.700302,
    ]);

    Event::assertDispatched(ServiceBookingPartnerLocationUpdated::class, function (ServiceBookingPartnerLocationUpdated $event) use ($booking) {
        return $event->booking->id === $booking->id;
    });
});

it('lets patient fetch latest tracking snapshot for their service booking', function () {
    $patient = User::factory()->create(['role' => 'pasien']);
    $partner = User::factory()->create(['role' => 'mitra']);

    PartnerProfile::create([
        'user_id' => $partner->id,
        'profession' => 'perawat',
        'verification_status' => 'verified',
    ]);

    $service = Service::create([
        'service_code' => 'SVC-TRACKING-002',
        'name' => 'Homecare Tracking Snapshot',
        'service_type' => 'procedure',
        'base_price' => 100000,
        'is_active' => true,
    ]);

    $booking = ServiceBooking::create([
        'booking_code' => 'SVC-TRACK-002',
        'service_id' => $service->id,
        'patient_user_id' => $patient->id,
        'assigned_partner_user_id' => $partner->id,
        'status' => 'on_the_way',
        'total_amount' => 100000,
    ]);

    ServiceBookingPartnerLocation::create([
        'service_booking_id' => $booking->id,
        'partner_user_id' => $partner->id,
        'latitude' => -8.172357,
        'longitude' => 113.700302,
        'accuracy_meters' => 10,
        'recorded_at' => now(),
    ]);

    Sanctum::actingAs($patient);

    $this->getJson("/api/patient/service-bookings/{$booking->id}/tracking")
        ->assertOk()
        ->assertJsonPath('data.service_booking_id', $booking->id)
        ->assertJsonPath('data.partner_location.latitude', '-8.1723570')
        ->assertJsonPath('data.channel', "private-service-booking.{$booking->id}.tracking")
        ->assertJsonPath('data.event', 'service-booking.location.updated');
});

it('blocks other partners from updating booking tracking location', function () {
    $patient = User::factory()->create(['role' => 'pasien']);
    $assignedPartner = User::factory()->create(['role' => 'mitra']);
    $otherPartner = User::factory()->create(['role' => 'mitra']);

    foreach ([$assignedPartner, $otherPartner] as $partner) {
        PartnerProfile::create([
            'user_id' => $partner->id,
            'profession' => 'perawat',
            'verification_status' => 'verified',
        ]);
    }

    $service = Service::create([
        'service_code' => 'SVC-TRACKING-003',
        'name' => 'Homecare Tracking Guard',
        'service_type' => 'procedure',
        'base_price' => 100000,
        'is_active' => true,
    ]);

    $booking = ServiceBooking::create([
        'booking_code' => 'SVC-TRACK-003',
        'service_id' => $service->id,
        'patient_user_id' => $patient->id,
        'assigned_partner_user_id' => $assignedPartner->id,
        'status' => 'on_the_way',
        'total_amount' => 100000,
    ]);

    Sanctum::actingAs($otherPartner);

    $this->patchJson("/api/mitra/service-bookings/{$booking->id}/location", [
        'latitude' => -8.172357,
        'longitude' => 113.700302,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('service_booking');
});
