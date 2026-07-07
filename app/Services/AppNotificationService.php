<?php

namespace App\Services;

use App\Events\AppNotificationCreated;
use App\Models\AppNotification;
use App\Models\User;

class AppNotificationService
{
    /**
     * @param array<string, mixed> $payload
     */
    public function send(User|int $user, array $payload): AppNotification
    {
        $userId = $user instanceof User ? $user->id : $user;

        $notification = AppNotification::create([
            'user_id' => $userId,
            'type' => $payload['type'],
            'title' => $payload['title'],
            'body' => $payload['body'] ?? null,
            'action_url' => $payload['action_url'] ?? null,
            'reference_type' => $payload['reference_type'] ?? null,
            'reference_id' => $payload['reference_id'] ?? null,
            'data' => $payload['data'] ?? null,
        ]);

        AppNotificationCreated::dispatch($notification);

        return $notification;
    }
}
