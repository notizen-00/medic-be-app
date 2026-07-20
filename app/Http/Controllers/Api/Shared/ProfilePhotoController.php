<?php

namespace App\Http\Controllers\Api\Shared;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class ProfilePhotoController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'profile_photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        /** @var User $user */
        $user = $request->user();
        /** @var UploadedFile $file */
        $file = $validated['profile_photo'];

        $this->deleteExistingPhoto($user);

        $user->forceFill([
            'profile_photo_path' => $file->store("users/{$user->id}/profile", 'public'),
        ])->save();

        return response()->json([
            'message' => 'Foto profil berhasil diperbarui.',
            'data' => $user->fresh(),
        ]);
    }

    public function destroy(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $this->deleteExistingPhoto($user);

        $user->forceFill([
            'profile_photo_path' => null,
        ])->save();

        return response()->json([
            'message' => 'Foto profil berhasil dihapus.',
            'data' => $user->fresh(),
        ]);
    }

    private function deleteExistingPhoto(User $user): void
    {
        if ($user->profile_photo_path && ! str_starts_with($user->profile_photo_path, 'http')) {
            Storage::disk('public')->delete($user->profile_photo_path);
        }
    }
}
