<?php

namespace App\Events;

use App\Models\ServiceBooking;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ServiceBookingMatched implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public ServiceBooking $booking,
        public array $matchmaking = []
    ) {
        $this->booking->loadMissing(['service', 'patient', 'patientMember', 'assignedPartner.partnerProfile', 'address']);
    }

    /**
     * @return array<int, \Illuminate\Broadcasting\PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('partner.'.$this->booking->assigned_partner_user_id.'.service-bookings'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'service-booking.matched';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $address = $this->booking->serviceAddress();

        return [
            'booking' => [
                'id' => $this->booking->id,
                'booking_code' => $this->booking->booking_code,
                'patient_member_id' => $this->booking->patient_member_id,
                'status' => $this->booking->status,
                'scheduled_at' => $this->booking->scheduled_at?->toISOString(),
                'total_amount' => $this->booking->total_amount,
                'notes' => $this->booking->notes,
                'service' => $this->booking->service ? [
                    'id' => $this->booking->service->id,
                    'name' => $this->booking->service->name,
                    'service_type' => $this->booking->service->service_type,
                ] : null,
                'patient' => $this->booking->patient ? [
                    'id' => $this->booking->patient->id,
                    'name' => $this->booking->patient->name,
                    'phone' => $this->booking->patient->phone,
                ] : null,
                'patient_member' => $this->booking->patientMember ? [
                    'id' => $this->booking->patientMember->id,
                    'name' => $this->booking->patientMember->name,
                    'relationship' => $this->booking->patientMember->relationship,
                    'phone' => $this->booking->patientMember->phone,
                ] : null,
                'address' => $address ? [
                    'id' => $address->id,
                    'label' => $address->label,
                    'address' => $address->address,
                    'latitude' => $address->latitude,
                    'longitude' => $address->longitude,
                ] : null,
            ],
            'matchmaking' => $this->matchmaking,
            'created_at' => now()->toISOString(),
        ];
    }
}
