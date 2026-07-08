<?php

namespace App\Events;

use App\Models\ServiceBooking;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ServiceBookingPartnerLocationUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public ServiceBooking $booking
    ) {
        $this->booking->loadMissing(['assignedPartner', 'patient', 'partnerLocation']);
    }

    /**
     * @return array<int, \Illuminate\Broadcasting\PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('service-booking.'.$this->booking->id.'.tracking'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'service-booking.location.updated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $location = $this->booking->partnerLocation;

        return [
            'service_booking_id' => $this->booking->id,
            'booking_code' => $this->booking->booking_code,
            'status' => $this->booking->status,
            'patient_user_id' => $this->booking->patient_user_id,
            'assigned_partner_user_id' => $this->booking->assigned_partner_user_id,
            'partner' => $this->booking->assignedPartner ? [
                'id' => $this->booking->assignedPartner->id,
                'name' => $this->booking->assignedPartner->name,
                'phone' => $this->booking->assignedPartner->phone,
            ] : null,
            'location' => [
                'latitude' => $location?->latitude,
                'longitude' => $location?->longitude,
                'accuracy_meters' => $location?->accuracy_meters,
                'heading' => $location?->heading,
                'speed_mps' => $location?->speed_mps,
                'updated_at' => $location?->recorded_at?->toISOString(),
            ],
        ];
    }
}
