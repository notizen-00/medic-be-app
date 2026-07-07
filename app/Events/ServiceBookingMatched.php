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
        $this->booking->loadMissing(['service', 'patient', 'assignedPartner.partnerProfile', 'address']);
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
        return [
            'booking' => [
                'id' => $this->booking->id,
                'booking_code' => $this->booking->booking_code,
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
                'address' => $this->booking->address ? [
                    'id' => $this->booking->address->id,
                    'label' => $this->booking->address->label,
                    'address' => $this->booking->address->address,
                    'latitude' => $this->booking->address->latitude,
                    'longitude' => $this->booking->address->longitude,
                ] : null,
            ],
            'matchmaking' => $this->matchmaking,
            'created_at' => now()->toISOString(),
        ];
    }
}
