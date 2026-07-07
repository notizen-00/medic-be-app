<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Pharmacy;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    public function adminApotiks(Request $request): JsonResponse
    {
        $this->ensureAdminAccess($request);

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $perPage = $this->resolvePerPage($request);

        $apotiks = Pharmacy::query()
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
            ->paginate($perPage)
            ->withQueryString();

        return response()->json([
            'message' => 'Daftar semua apotik berhasil diambil.',
            'data' => $apotiks,
        ]);
    }

    public function apotiks(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'is_available' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $perPage = $this->resolvePerPage($request);

        $apotiks = Pharmacy::query()
            ->whereHas('owner', fn ($query) => $query->where('role', 'mitra'))
            ->when(
                $validated['search'] ?? null,
                fn ($query, $search) => $query->where(function ($searchQuery) use ($search) {
                    $searchQuery->whereHas('profile', fn ($profileQuery) => $profileQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('address', 'like', "%{$search}%"))
                        ->orWhereHas('owner', fn ($ownerQuery) => $ownerQuery->where('name', 'like', "%{$search}%"));
                })
            )
            ->when(
                array_key_exists('is_available', $validated),
                fn ($query) => $query->where('is_active', $validated['is_available'])
            )
            ->with(['owner', 'profile'])
            ->latest()
            ->paginate($perPage)
            ->withQueryString();

        return response()->json([
            'message' => 'Daftar apotik berhasil diambil.',
            'data' => $apotiks,
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'role' => ['nullable', 'in:pasien,mitra'],
            'search' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $perPage = $this->resolvePerPage($request);

        $users = User::query()
            ->when(
                $validated['role'] ?? null,
                fn ($query, $role) => $query->where('role', $role)
            )
            ->when(
                $validated['search'] ?? null,
                fn ($query, $search) => $query->where('name', 'like', "%{$search}%")
            )
            ->with(['patientProfile', 'patientMembers', 'partnerProfile', 'courierProfile', 'pharmacy'])
            ->latest()
            ->paginate($perPage)
            ->withQueryString();

        return response()->json([
            'message' => 'Daftar user berhasil diambil.',
            'data' => $users,
        ]);
    }

    public function show(User $user): JsonResponse
    {
        $user->load(['patientProfile', 'patientMembers', 'partnerProfile', 'courierProfile', 'pharmacy']);

        return response()->json([
            'message' => 'Detail user berhasil diambil.',
            'data' => $user,
        ]);
    }

    private function ensureAdminAccess(Request $request): void
    {
        /** @var User|null $user */
        $user = $request->user();

        if (! $user || $user->role !== 'admin') {
            throw ValidationException::withMessages([
                'user' => ['Hanya akun admin yang dapat mengakses endpoint ini.'],
            ]);
        }
    }
}
