<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'role' => ['nullable', 'in:pasien,dokter,apotik,kurir'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $users = User::query()
            ->when(
                $validated['role'] ?? null,
                fn ($query, $role) => $query->where('role', $role)
            )
            ->when(
                $validated['search'] ?? null,
                fn ($query, $search) => $query->where('name', 'like', "%{$search}%")
            )
            ->with(['patientProfile', 'partnerProfile', 'courierProfile'])
            ->latest()
            ->get();

        return response()->json([
            'message' => 'Daftar user berhasil diambil.',
            'data' => $users,
        ]);
    }

    public function show(User $user): JsonResponse
    {
        $user->load(['patientProfile', 'partnerProfile', 'courierProfile']);

        return response()->json([
            'message' => 'Detail user berhasil diambil.',
            'data' => $user,
        ]);
    }
}
