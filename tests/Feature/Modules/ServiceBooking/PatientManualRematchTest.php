<?php

use App\Events\ServiceBookingMatched;
use App\Models\PartnerProfile;
use App\Models\PartnerService;
use App\Models\PatientMember;
use App\Models\Payment;
use App\Models\Service;
use App\Models\ServiceBooking;
use App\Models\ServiceBookingFeeSetting;
use App\Models\ServiceBookingHistory;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;

function createManualRematchService(): Service
{
    return Service::create([
        'service_code' => 'SVC-MANUAL-REMATCH-'.strtoupper(str()->random(6)),
        'name' => 'Pasang Infus Manual Rematch',
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

function createManualRematchPatientMember(User $patient): PatientMember
{
    return PatientMember::create([
        'owner_user_id' => $patient->id,
        'name' => 'Pasien Manual Rematch',
        'relationship' => 'self',
        'recipient_name' => 'Pasien Manual Rematch',
        'recipient_phone' => '081234567890',
        'address' => 'Jl. Manual Rematch',
        'latitude' => -8.1700000,
        'longitude' => 113.7000000,
    ]);
}

function createManualRematchPartner(Service $service, float $latitude, float $longitude): User
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

it('lets patient manually rematch a pending service booking without assigned partner', function () {
    ServiceBookingFeeSetting::create([
        'transport_distance_threshold_km' => 1,
        'transport_fee_per_visit' => 25000,
        'hospital_meal_fee_per_visit' => 0,
        'is_active' => true,
    ]);

    $patient = User::factory()->create(['role' => 'pasien']);
    $rejectedPartner = User::factory()->create(['role' => 'mitra']);
    $service = createManualRematchService();
    $patientMember = createManualRematchPatientMember($patient);
    $replacementPartner = createManualRematchPartner($service, -8.2000000, 113.7000000);

    $booking = ServiceBooking::create([
        'booking_code' => 'SVC-MANUAL-REMATCH-001',
        'service_id' => $service->id,
        'patient_user_id' => $patient->id,
        'patient_member_id' => $patientMember->id,
        'assigned_partner_user_id' => null,
        'status' => 'pending',
        'visit_plan' => 'once',
        'visit_count' => 1,
        'care_mode' => 'visit',
        'location_type' => 'home',
        'subtotal' => 100000,
        'discount_amount' => 0,
        'markup_amount' => 0,
        'total_amount' => 100000,
    ]);

    Payment::create([
        'service_booking_id' => $booking->id,
        'patient_user_id' => $patient->id,
        'payment_code' => 'PAY-MANUAL-REMATCH-001',
        'status' => 'pending',
        'amount' => 100000,
    ]);

    ServiceBookingHistory::create([
        'service_booking_id' => $booking->id,
        'actor_user_id' => $rejectedPartner->id,
        'type' => 'status',
        'title' => 'Mitra menolak pesanan',
        'description' => 'Mitra menolak pesanan layanan.',
        'meta' => [
            'status' => 'rejected_by_partner',
            'type' => 'matchmaking',
            'rejected_partner_user_id' => $rejectedPartner->id,
        ],
        'handled_at' => now(),
    ]);

    Event::fake([ServiceBookingMatched::class]);
    Sanctum::actingAs($patient);

    $this->postJson("/api/patient/service-bookings/{$booking->id}/rematch", [
        'notes' => 'Cari mitra lagi.',
    ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('matchmaking_status', 'rematched_waiting_partner_acceptance')
        ->assertJsonPath('data.assigned_partner_user_id', $replacementPartner->id)
        ->assertJsonPath('data.transport_fee', '25000.00')
        ->assertJsonPath('data.total_amount', '125000.00')
        ->assertJsonPath('data.payment.amount', '125000.00');

    Event::assertDispatched(ServiceBookingMatched::class, function (ServiceBookingMatched $event) use ($booking, $replacementPartner) {
        return (int) $event->booking->id === (int) $booking->id
            && (int) $event->booking->assigned_partner_user_id === (int) $replacementPartner->id;
    });

    $this->assertDatabaseHas('service_bookings', [
        'id' => $booking->id,
        'assigned_partner_user_id' => $replacementPartner->id,
        'transport_fee' => 25000,
        'total_amount' => 125000,
    ]);

    $this->assertDatabaseHas('payments', [
        'service_booking_id' => $booking->id,
        'status' => 'pending',
        'amount' => 125000,
    ]);
});

it('prevents patient manual rematch while a partner is still assigned', function () {
    $patient = User::factory()->create(['role' => 'pasien']);
    $service = createManualRematchService();
    $patientMember = createManualRematchPatientMember($patient);
    $assignedPartner = createManualRematchPartner($service, -8.1710000, 113.7010000);

    $booking = ServiceBooking::create([
        'booking_code' => 'SVC-MANUAL-REMATCH-002',
        'service_id' => $service->id,
        'patient_user_id' => $patient->id,
        'patient_member_id' => $patientMember->id,
        'assigned_partner_user_id' => $assignedPartner->id,
        'status' => 'pending',
        'subtotal' => 100000,
        'total_amount' => 100000,
    ]);

    Payment::create([
        'service_booking_id' => $booking->id,
        'patient_user_id' => $patient->id,
        'payment_code' => 'PAY-MANUAL-REMATCH-002',
        'status' => 'pending',
        'amount' => 100000,
    ]);

    Sanctum::actingAs($patient);

    $this->postJson("/api/patient/service-bookings/{$booking->id}/rematch")
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['service_booking']);
});
