<?php

namespace App\Http\Controllers\Api\Shared;

use App\Http\Controllers\Controller;
use App\Models\AppNotification;
use App\Services\AppNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', 'in:read,unread'],
            'type' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $perPage = $this->resolvePerPage($request);

        $notifications = AppNotification::query()
            ->where('user_id', $request->user()->id)
            ->when(
                ($validated['status'] ?? null) === 'read',
                fn ($query) => $query->whereNotNull('read_at')
            )
            ->when(
                ($validated['status'] ?? null) === 'unread',
                fn ($query) => $query->whereNull('read_at')
            )
            ->when(
                $validated['type'] ?? null,
                fn ($query, $type) => $query->where('type', $type)
            )
            ->latest()
            ->paginate($perPage)
            ->withQueryString();

        return response()->json([
            'message' => 'Daftar notifikasi berhasil diambil.',
            'data' => $notifications,
            'unread_count' => $this->unreadCountFor($request),
        ]);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json([
            'message' => 'Jumlah notifikasi belum dibaca berhasil diambil.',
            'data' => [
                'unread_count' => $this->unreadCountFor($request),
            ],
        ]);
    }

    public function markAsRead(Request $request, AppNotification $notification): JsonResponse
    {
        $this->ensureNotificationOwner($request, $notification);
        $notification->markAsRead();

        return response()->json([
            'message' => 'Notifikasi berhasil ditandai sudah dibaca.',
            'data' => $notification->fresh(),
            'unread_count' => $this->unreadCountFor($request),
        ]);
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        AppNotification::query()
            ->where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json([
            'message' => 'Semua notifikasi berhasil ditandai sudah dibaca.',
            'data' => [
                'unread_count' => 0,
            ],
        ]);
    }

    public function destroy(Request $request, AppNotification $notification): JsonResponse
    {
        $this->ensureNotificationOwner($request, $notification);
        $notification->delete();

        return response()->json([
            'message' => 'Notifikasi berhasil dihapus.',
            'unread_count' => $this->unreadCountFor($request),
        ]);
    }

    public function store(Request $request, AppNotificationService $notifications): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['required', 'string', 'max:100'],
            'title' => ['required', 'string', 'max:255'],
            'body' => ['nullable', 'string'],
            'action_url' => ['nullable', 'string', 'max:255'],
            'reference_type' => ['nullable', 'string', 'max:100'],
            'reference_id' => ['nullable', 'integer', 'min:1'],
            'data' => ['nullable', 'array'],
        ]);

        $notification = $notifications->send($request->user(), $validated);

        return response()->json([
            'message' => 'Notifikasi test berhasil dibuat.',
            'data' => $notification,
            'unread_count' => $this->unreadCountFor($request),
        ], 201);
    }

    private function ensureNotificationOwner(Request $request, AppNotification $notification): void
    {
        if ($notification->user_id !== $request->user()->id) {
            throw ValidationException::withMessages([
                'notification' => ['Notifikasi ini tidak berada dalam akses user yang sedang login.'],
            ]);
        }
    }

    private function unreadCountFor(Request $request): int
    {
        return AppNotification::query()
            ->where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->count();
    }
}
