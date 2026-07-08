<?php

namespace App\Http\Controllers\Api\Mitra;

use App\Http\Controllers\Controller;
use App\Models\PartnerProfile;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user();

        if (! $user || $user->role !== 'mitra') {
            throw ValidationException::withMessages([
                'user' => ['Hanya akun mitra yang dapat mengakses endpoint ini.'],
            ]);
        }

        $user->load('partnerProfile');

        return response()->json([
            'message' => 'Profil mitra berhasil diambil.',
            'data' => $user,
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user();

        if (! $user || $user->role !== 'mitra') {
            throw ValidationException::withMessages([
                'user' => ['Hanya akun mitra yang dapat mengakses endpoint ini.'],
            ]);
        }

        /** @var PartnerProfile|null $partnerProfile */
        $partnerProfile = $user->partnerProfile;

        if (! $partnerProfile) {
            throw ValidationException::withMessages([
                'partner_profile' => ['Profil mitra belum tersedia.'],
            ]);
        }

        $validated = $request->validate([
            'specialization' => ['nullable', 'string', 'max:255'],
            'license_number' => ['nullable', 'string', 'max:255', 'unique:partner_profiles,license_number,'.$partnerProfile->id],
            'work_location' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'years_of_experience' => ['nullable', 'integer', 'min:0'],
            'consultation_fee' => ['nullable', 'numeric', 'min:0'],
            'bio' => ['nullable', 'string'],
            'is_available' => ['nullable', 'boolean'],
            'str_photo' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'ktp_photo' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);

        $updatePayload = collect($validated)
            ->except(['str_photo', 'ktp_photo'])
            ->toArray();

        if (($validated['str_photo'] ?? null) instanceof UploadedFile) {
            $updatePayload['str_photo_path'] = $this->storePartnerDocument($user->id, $validated['str_photo'], 'str');
        }

        if (($validated['ktp_photo'] ?? null) instanceof UploadedFile) {
            $updatePayload['ktp_photo_path'] = $this->storePartnerDocument($user->id, $validated['ktp_photo'], 'ktp');
        }

        $partnerProfile->update($updatePayload);
        $partnerProfile->refresh();

        return response()->json([
            'message' => 'Profil mitra berhasil diperbarui.',
            'data' => $user->load('partnerProfile'),
        ]);
    }

    public function toggleAvailability(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user();

        if (! $user || $user->role !== 'mitra') {
            throw ValidationException::withMessages([
                'user' => ['Hanya akun mitra yang dapat mengakses endpoint ini.'],
            ]);
        }

        /** @var PartnerProfile|null $partnerProfile */
        $partnerProfile = $user->partnerProfile;

        if (! $partnerProfile) {
            throw ValidationException::withMessages([
                'partner_profile' => ['Profil mitra belum tersedia.'],
            ]);
        }

        $validated = $request->validate([
            'is_available' => ['required', 'boolean'],
        ]);

        $partnerProfile->update(['is_available' => $validated['is_available']]);
        $partnerProfile->refresh();

        $status = $partnerProfile->is_available ? 'Aktif' : 'Tidak Aktif';

        return response()->json([
            'message' => "Status ketersediaan mitra berhasil diubah menjadi {$status}.",
            'data' => [
                'is_available' => $partnerProfile->is_available,
            ],
        ]);
    }

    private function storePartnerDocument(int $userId, UploadedFile $file, string $documentType): string
    {
        $extension = strtolower($file->getClientOriginalExtension() ?: 'jpg');
        $path = "partners/{$userId}/{$documentType}.{$extension}";

        Storage::disk('public')->putFileAs("partners/{$userId}", $file, "{$documentType}.{$extension}");

        return $path;
    }
}

