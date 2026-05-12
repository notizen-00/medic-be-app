<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\PartnerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PartnerServicesController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'partner_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'service_id' => ['nullable', 'integer', 'exists:services,id'],
            'is_active' => ['nullable', 'boolean'],
            'is_verified' => ['nullable', 'boolean'],
            'search' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $perPage = $this->resolvePerPage($request);

        $partnerServices = PartnerService::query()
            ->with(['service', 'partner.partnerProfile'])
            ->when(
                $validated['partner_user_id'] ?? null,
                fn ($query, $partnerUserId) => $query->where('partner_user_id', $partnerUserId)
            )
            ->when(
                $validated['service_id'] ?? null,
                fn ($query, $serviceId) => $query->where('service_id', $serviceId)
            )
            ->when(
                array_key_exists('is_active', $validated),
                fn ($query) => $query->where('is_active', $validated['is_active'])
            )
            ->when(
                array_key_exists('is_verified', $validated),
                fn ($query) => $query->where('is_verified', $validated['is_verified'])
            )
            ->when(
                $validated['search'] ?? null,
                fn ($query, $search) => $query->where(function ($searchQuery) use ($search) {
                    $searchQuery->where('notes', 'like', "%{$search}%")
                        ->orWhereHas('service', fn ($serviceQuery) => $serviceQuery
                            ->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('partner', fn ($partnerQuery) => $partnerQuery
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%"));
                })
            )
            ->latest()
            ->paginate($perPage)
            ->withQueryString();

        return response()->json([
            'message' => 'Daftar semua layanan mitra admin berhasil diambil.',
            'data' => $partnerServices,
        ]);
    }
}
