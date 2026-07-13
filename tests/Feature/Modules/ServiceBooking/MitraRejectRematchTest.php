<?php

use App\Events\ServiceBookingMatched;
use App\Models\PartnerProfile;
use App\Models\PartnerService;
use App\Models\PatientMember;
use App\Models\Service;
use App\Models\ServiceBooking;
use App\Models\ServiceBookingFeeSetting;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;

function createVerifiedNurseForRejectRematch(Service $service, float $latitude, float $longitude): User
{
    $partner = User::factory()->create(['role' => 'mitra']);

    PartnerProfile::create([
        'user_id' => $partner->id,
        'profession' => 'perawat',
        'latitude' => $latitude,
        'longitude' => $longitude,
        'years_of_experience' => 3,
        'is_available' => true,
        'verification_status' => 'verified',
        'verified_at' => now(),
    ]);

    PartnerService::create([
        'service_id' => $service->id,
        'partner_user_id' => $partner->id,
        'price' => $service->base_price,
        'coverage_radius_km' => 30,
        'is_active' => true,
        'is_verified' => true,
        'is_available' => true,
    ]);

    return $partner;
}

function createRejectRematchPatientMember(User $patient): PatientMember
{
    return PatientMember::create([
        'owner_user_id' => $patient->id,
        'name' => 'Pasien Rematch',
        'relationship' => 'self',
        'recipient_name' => 'Pasien Rematch',
        'recipient_phone' => '081234567890',
        'address' => 'Jl. Rematch',
        'latitude' => -8.1700000,
        'longitude' => 113.7000000,
    ]);
}

function createRejectRematchService(): Service
{
    return Service::create([
        'service_code' => 'SVC-REJECT-REMATCH-'.strtoupper(str()->random(6)),
        'name' => 'Pasang Infus Rematch',
        'service_type' => 'procedure',
        'service_mode' => 'visit',
        'base_price' => 100000,
        'requires_address' => true,
        'requires_schedule' => false,
        'requires_matchmaking' => true,
        'is_active' => true,
        'is_homecare' => true,
    ]);
}

it('rematches service booking to another partner when assigned mitra rejects before acceptance', function () {
    ServiceBookingFeeSetting::create([
        'transport_distance_threshold_km' => 1,
        'transport_fee_per_visit' => 25000,
        'hospital_meal_fee_per_visit' => 0,
        'is_active' => true,
    ]);

    $patient = User::factory()->create(['role' => 'pasien']);
    $patientMember = createRejectRematchPatientMember($patient);
    $service = createRejectRematchService();
    $firstPartner = createVerifiedNurseForRejectRematch($service, -8.1710000, 113.7010000);
    $secondPartner = createVerifiedNurseForRejectRematch($service, -8.2000000, 113.7000000);

    Sanctum::actingAs($patient);

    $bookingId = $this->postJson('/api/patient/service-bookings', [
        'service_id' => $service->id,
        'patient_member_id' => $patientMember->id,
    ])
        ->assertCreated()
        ->assertJsonPath('data.booking.assigned_partner_user_id', $firstPartner->id)
        ->assertJsonPath('data.pricing.transport_fee', '0.00')
        ->json('data.booking.id');

    Event::fake([ServiceBookingMatched::class]);
    Sanctum::actingAs($firstPartner);

    $this->patchJson("/api/mitra/service-bookings/{$bookingId}/reject", [
        'notes' => 'Jadwal tidak tersedia.',
    ])
        ->assertOk()
        ->assertJsonPath('matchmaking_status', 'rematched_waiting_partner_acceptance')
        ->assertJsonPath('data.assigned_partner_user_id', $secondPartner->id)
        ->assertJsonPath('data.transport_fee', '25000.00')
        ->assertJsonPath('data.total_amount', '125000.00')
        ->assertJsonPath('data.partner_payout_amount', 125000);

    Event::assertDispatched(ServiceBookingMatched::class, function (ServiceBookingMatched $event) use ($bookingId, $secondPartner) {
        return (int) $event->booking->id === (int) $bookingId
            && (int) $event->booking->assigned_partner_user_id === (int) $secondPartner->id;
    });

    $this->assertDatabaseHas('service_bookings', [
        'id' => $bookingId,
        'assigned_partner_user_id' => $secondPartner->id,
        'status' => 'pending',
        'transport_fee' => 25000,
        'total_amount' => 125000,
    ]);

    $this->assertDatabaseHas('payments', [
        'service_booking_id' => $bookingId,
        'status' => 'pending',
        'amount' => 125000,
    ]);

    $this->assertDatabaseHas('service_booking_histories', [
        'service_booking_id' => $bookingId,
        'actor_user_id' => $firstPartner->id,
        'title' => 'Mitra menolak pesanan',
    ]);

    $this->getJson('/api/mitra/service-bookings')
        ->assertOk()
        ->assertJsonMissing(['id' => $bookingId]);

    Sanctum::actingAs($secondPartner);

    $this->getJson('/api/mitra/service-bookings')
        ->assertOk()
        ->assertJsonFragment(['id' => $bookingId]);
});

it('keeps booking pending without assigned partner when no replacement partner is available', function () {
    $patient = User::factory()->create(['role' => 'pasien']);
    $patientMember = createRejectRematchPatientMember($patient);
    $service = createRejectRematchService();
    $partner = createVerifiedNurseForRejectRematch($service, -8.1710000, 113.7010000);

    Sanctum::actingAs($patient);

    $bookingId = $this->postJson('/api/patient/service-bookings', [
        'service_id' => $service->id,
        'patient_member_id' => $patientMember->id,
    ])
        ->assertCreated()
        ->assertJsonPath('data.booking.assigned_partner_user_id', $partner->id)
        ->json('data.booking.id');

    Event::fake([ServiceBookingMatched::class]);
    Sanctum::actingAs($partner);

    $this->patchJson("/api/mitra/service-bookings/{$bookingId}/reject")
        ->assertOk()
        ->assertJsonPath('matchmaking_status', 'waiting_partner_available')
        ->assertJsonPath('data.assigned_partner_user_id', null)
        ->assertJsonPath('data.payment', null);

    Event::assertNotDispatched(ServiceBookingMatched::class);

    $this->assertDatabaseHas('service_bookings', [
        'id' => $bookingId,
        'assigned_partner_user_id' => null,
        'status' => 'pending',
    ]);

    $this->assertDatabaseMissing('payments', [
        'service_booking_id' => $bookingId,
        'status' => 'pending',
    ]);

    expect(ServiceBooking::find($bookingId)->histories()->where('title', 'Belum ada mitra pengganti')->exists())->toBeTrue();
});
