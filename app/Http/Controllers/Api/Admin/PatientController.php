<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\PartnerProfile;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;


class PatientController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'is_available' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $perPage = $this->resolvePerPage($request);

        $partners = User::query()
            ->where('role', 'pasien')
            ->whereHas('patientProfile', function ($query) use ($validated) {
                // $query->when(
                //     $validated['profession'] ?? null,
                //     fn($profileQuery, $profession) => $profileQuery->where('profession', $profession)
                // )
                // $query->when(
                //     array_key_exists('is_available', $validated),
                //     fn($profileQuery) => $profileQuery->where('is_available', $validated['is_available'])
                // );
            })
            ->when(
                $validated['search'] ?? null,
                fn($query, $search) => $query->where(function ($searchQuery) use ($search) {
                    $searchQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhereHas('patientProfile', fn($profileQuery) => $profileQuery
                            ->where('specialization', 'like', "%{$search}%")
                            ->orWhere('work_location', 'like', "%{$search}%")
                            ->orWhere('license_number', 'like', "%{$search}%"));
                })
            )
            ->with(['patientProfile'])
            ->latest()
            ->paginate($perPage)
            ->withQueryString();

        return response()->json([
            'message' => 'Daftar semua pasien berhasil diambil.',
            'data' => $partners,
        ]);
    }

    public function show(User $user): JsonResponse
    {
        $partner = User::query()
            ->where('role', 'pasien')
            ->where('id', $user->id)
            ->with(['patientProfile'])
            ->withCount(['patientConsultations', 'patientServices', 'patientServiceBookings'])
            ->firstOrFail();

        return response()->json([
            'message' => 'Detail pasien berhasil diambil.',
            'data' => $partner,
        ]);
    }
}
