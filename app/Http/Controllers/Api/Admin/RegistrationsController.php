<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Pharmacy;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RegistrationsController extends Controller
{
    public function partnerRegistrations(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['nullable', 'in:all,dokter,perawat,bidan,apotek'],
            'search' => ['nullable', 'string', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
            'is_available' => ['nullable', 'boolean'],
        ]);

        $type = $validated['type'] ?? 'all';

        $medicalPartners = collect();
        $pharmacies = collect();

        if (in_array($type, ['all', 'dokter', 'perawat', 'bidan'], true)) {
            $medicalPartners = User::query()
                ->where('role', 'mitra')
                ->whereHas('partnerProfile', function ($query) use ($type, $validated) {
                    $query
                        ->when(
                            $type !== 'all',
                            fn ($profileQuery) => $profileQuery->where('profession', $type)
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
                                ->orWhere('license_number', 'like', "%{$search}%")
                                ->orWhere('work_location', 'like', "%{$search}%"));
                    })
                )
                ->with(['partnerProfile'])
                ->withCount(['partnerConsultations', 'partnerServices'])
                ->latest()
                ->get()
                ->map(function (User $user) {
                    $user->setAttribute('registration_type', 'mitra_medis');

                    return $user;
                });
        }

        if (in_array($type, ['all', 'apotek'], true)) {
            $pharmacies = Pharmacy::query()
                ->when(
                    $validated['search'] ?? null,
                    fn ($query, $search) => $query->where(function ($searchQuery) use ($search) {
                        $searchQuery->whereHas('profile', fn ($profileQuery) => $profileQuery
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('license_number', 'like', "%{$search}%")
                            ->orWhere('address', 'like', "%{$search}%"))
                            ->orWhereHas('owner', fn ($ownerQuery) => $ownerQuery
                                ->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%")
                                ->orWhere('phone', 'like', "%{$search}%"));
                    })
                )
                ->when(
                    array_key_exists('is_active', $validated),
                    fn ($query) => $query->where('is_active', $validated['is_active'])
                )
                ->with(['owner', 'profile'])
                ->withCount(['products', 'orders'])
                ->latest()
                ->get()
                ->map(function (Pharmacy $pharmacy) {
                    $pharmacy->setAttribute('registration_type', 'apotek');

                    return $pharmacy;
                });
        }

        return response()->json([
            'message' => 'Daftar pendaftaran mitra admin berhasil diambil.',
            'data' => [
                'medical_partners' => $medicalPartners->values(),
                'pharmacies' => $pharmacies->values(),
            ],
        ]);
    }
}
