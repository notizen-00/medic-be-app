<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\PartnerProfile;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PartnersController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'profession' => ['nullable', 'in:dokter,perawat,bidan'],
            'search' => ['nullable', 'string', 'max:100'],
            'is_available' => ['nullable', 'boolean'],
        ]);

        $partners = User::query()
            ->where('role', 'mitra')
            ->whereHas('partnerProfile', function ($query) use ($validated) {
                $query->when(
                    $validated['profession'] ?? null,
                    fn ($profileQuery, $profession) => $profileQuery->where('profession', $profession)
                )
                    ->when(
                        array_key_exists('is_available', $validated),
                        fn ($profileQuery) => $profileQuery->where('is_available', $validated['is_available'])
                    );
            })
            ->when(
                $validated['search'] ?? null,
                fn ($query, $search) => $query->where(function ($searchQuery) use ($search) {
                    $searchQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhereHas('partnerProfile', fn ($profileQuery) => $profileQuery
                            ->where('specialization', 'like', "%{$search}%")
                            ->orWhere('work_location', 'like', "%{$search}%")
                            ->orWhere('license_number', 'like', "%{$search}%"));
                })
            )
            ->with(['partnerProfile'])
            ->withCount(['partnerConsultations', 'partnerServices', 'partnerServiceBookings'])
            ->latest()
            ->get();

        return response()->json([
            'message' => 'Daftar semua mitra medis admin berhasil diambil.',
            'data' => $partners,
        ]);
    }

    public function doctors(Request $request): JsonResponse
    {
        $request->merge(['profession' => 'dokter']);

        return $this->index($request);
    }

    public function nurses(Request $request): JsonResponse
    {
        $request->merge(['profession' => 'perawat']);

        return $this->index($request);
    }

    public function midwives(Request $request): JsonResponse
    {
        $request->merge(['profession' => 'bidan']);

        return $this->index($request);
    }

    public function verify(Request $request, User $user): JsonResponse
    {
        /** @var User|null $admin */
        $admin = $request->user();

        if (! $admin || $admin->role !== 'admin') {
            throw ValidationException::withMessages([
                'user' => ['Hanya akun admin yang dapat memverifikasi mitra.'],
            ]);
        }

        if ($user->role !== 'mitra') {
            throw ValidationException::withMessages([
                'partner_user_id' => ['User bukan akun mitra.'],
            ]);
        }

        /** @var PartnerProfile|null $partnerProfile */
        $partnerProfile = $user->partnerProfile;

        if (! $partnerProfile) {
            throw ValidationException::withMessages([
                'partner_profile' => ['Profil mitra tidak ditemukan.'],
            ]);
        }

        $partnerProfile->update([
            'verification_status' => 'verified',
            'verified_at' => now(),
            'verified_by_user_id' => $admin->id,
        ]);

        return response()->json([
            'message' => 'Mitra berhasil diverifikasi.',
            'data' => $user->load('partnerProfile'),
        ]);
    }
}
