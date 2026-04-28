<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Pharmacy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PharmaciesController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
        ]);

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
            ->get();

        return response()->json([
            'message' => 'Daftar semua apotek admin berhasil diambil.',
            'data' => $pharmacies,
        ]);
    }
}
