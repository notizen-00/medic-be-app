<?php

namespace App\Http\Controllers\Api\Shared;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PartnerDocumentController extends Controller
{
    public function show(Request $request, User $user, string $type): BinaryFileResponse
    {
        $viewer = $request->user();

        if (! $viewer) {
            throw ValidationException::withMessages([
                'user' => ['User login tidak ditemukan.'],
            ]);
        }

        if ($viewer->role !== 'admin' && $viewer->id !== $user->id) {
            throw ValidationException::withMessages([
                'user' => ['Anda tidak memiliki akses untuk melihat dokumen ini.'],
            ]);
        }

        if (! in_array($type, ['ktp', 'str'], true)) {
            throw ValidationException::withMessages([
                'type' => ['Tipe dokumen tidak valid. Gunakan `ktp` atau `str`.'],
            ]);
        }

        $user->loadMissing('partnerProfile');

        if (! $user->partnerProfile) {
            throw ValidationException::withMessages([
                'partner_profile' => ['Profil mitra tidak ditemukan.'],
            ]);
        }

        $path = $type === 'ktp'
            ? $user->partnerProfile->ktp_photo_path
            : $user->partnerProfile->str_photo_path;

        if (! $path) {
            throw ValidationException::withMessages([
                'document' => ['Dokumen tidak tersedia.'],
            ]);
        }

        $disk = Storage::disk('private');

        if (! $disk->exists($path)) {
            throw ValidationException::withMessages([
                'document' => ['File dokumen tidak ditemukan.'],
            ]);
        }

        $absolutePath = $disk->path($path);
        $mimeType = $disk->mimeType($path) ?: 'application/octet-stream';

        return response()->file($absolutePath, [
            'Content-Type' => $mimeType,
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
