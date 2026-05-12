<?php

namespace App\Events;

use App\Models\ConsultationMessage;
use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ChatMessageCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @var ConsultationMessage
     */
    public $message;

    /**
     * @var User
     */
    public $sender;

    /**
     * Create a new event instance.
     */
    public function __construct(ConsultationMessage $message)
    {
        $this->message = $message->load(['sender', 'consultation']);
        $this->sender = $message->sender;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        $consultation = $this->message->consultation;

        return [
            // Channel private untuk consultation ini
            new PrivateChannel('consultation.' . $consultation->id),
        ];
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->message->id,
            'consultation_id' => $this->message->consultation_id,
            'sender_user_id' => $this->message->sender_user_id,
            'sender' => [
                'id' => $this->sender->id,
                'name' => $this->sender->name,
                'email' => $this->sender->email,
                'role' => $this->sender->role,
            ],
            'message_type' => $this->message->message_type,
            'message' => $this->message->message,
            'attachment_path' => $this->message->attachment_path,
            'read_at' => $this->message->read_at,
            'created_at' => $this->message->created_at->toISOString(),
        ];
    }

    /**
     * Get the name of the event to broadcast.
     */
    public function broadcastAs(): string
    {
        return 'chat.message.created';
    }
}
