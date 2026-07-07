<?php

use App\Models\Consultation;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

// Channel private untuk consultation chat
// Hanya patient dan dokter yang terkait dengan consultation ini yang bisa akses
Broadcast::channel('consultation.{consultationId}', function (User $user, int $consultationId) {
    $consultation = Consultation::find($consultationId);

    if (!$consultation) {
        return false;
    }

    // User harus merupakan patient atau dokter dari consultation ini
    if ($consultation->patient_user_id === $user->id || $consultation->partner_user_id === $user->id) {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'role' => $user->role,
            'consultation_id' => $consultationId,
        ];
    }

    return false;
});

// Channel private untuk semua consultation user
// Untuk notifikasi real-time semua chat user
Broadcast::channel('user.{userId}.chat', function (User $user, int $userId) {
    // User hanya bisa subscribe ke channel mereka sendiri
    return $user->id === $userId ? [
        'id' => $user->id,
        'name' => $user->name,
        'role' => $user->role,
    ] : false;
});

// Channel presence untuk online status
Broadcast::channel('online-users', function (User $user) {
    return [
        'id' => $user->id,
        'name' => $user->name,
        'role' => $user->role,
    ];
});

// Channel private untuk notifikasi booking layanan yang masuk ke mitra.
Broadcast::channel('partner.{partnerId}.service-bookings', function (User $user, int $partnerId) {
    if ($user->id !== $partnerId || $user->role !== 'mitra') {
        return false;
    }

    return [
        'id' => $user->id,
        'name' => $user->name,
        'role' => $user->role,
    ];
});
