<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Models\User;
use App\Services\ServicePartnerSelectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ServiceController extends Controller
{
    public function __construct(
        private readonly ServicePartnerSelectionService $servicePartnerSelectionService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'patient_address_id' => ['nullable', 'integer', 'exists:patient_addresses,id'],
            'service_type' => ['nullable', 'in:dokter_homecare,perawat_homecare,bidan_homecare,konsultasi_tindakan'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        return response()->json([
            'message' => 'Katalog layanan berhasil diambil.',
            'data' => $this->servicePartnerSelectionService->getServiceCatalog($validated),
        ]);
    }

    public function show(Service $service, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'patient_address_id' => ['nullable', 'integer', 'exists:patient_addresses,id'],
        ]);

        $catalog = $this->servicePartnerSelectionService
            ->getServiceCatalog([
                'patient_address_id' => $validated['patient_address_id'] ?? null,
            ])
            ->firstWhere('service.id', $service->id);

        return response()->json([
            'message' => 'Detail layanan berhasil diambil.',
            'data' => $catalog ?? ['service' => $service->load('partnerServices.partner.partnerProfile')],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->ensureAdminAccess($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'service_type' => ['required', 'in:dokter_homecare,perawat_homecare,bidan_homecare,konsultasi_tindakan'],
            'category' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'base_price' => ['required', 'numeric', 'min:0'],
            'duration_minutes' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['nullable', 'boolean'],
            'is_homecare' => ['nullable', 'boolean'],
        ]);

        $service = Service::create([
            'service_code' => 'SRV-' . now()->format('YmdHis') . '-' . str_pad((string) random_int(1, 999), 3, '0', STR_PAD_LEFT),
            'name' => $validated['name'],
            'service_type' => $validated['service_type'],
            'category' => $validated['category'] ?? null,
            'description' => $validated['description'] ?? null,
            'base_price' => $validated['base_price'],
            'duration_minutes' => $validated['duration_minutes'] ?? 60,
            'is_active' => $validated['is_active'] ?? true,
            'is_homecare' => $validated['is_homecare'] ?? true,
        ]);

        return response()->json([
            'message' => 'Master layanan berhasil dibuat.',
            'data' => $service,
        ], 201);
    }

    public function update(Request $request, Service $service): JsonResponse
    {
        $this->ensureAdminAccess($request);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'service_type' => ['sometimes', 'in:dokter_homecare,perawat_homecare,bidan_homecare,konsultasi_tindakan'],
            'category' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'base_price' => ['sometimes', 'numeric', 'min:0'],
            'duration_minutes' => ['sometimes', 'integer', 'min:1'],
            'is_active' => ['sometimes', 'boolean'],
            'is_homecare' => ['sometimes', 'boolean'],
        ]);

        $service->update($validated);

        return response()->json([
            'message' => 'Master layanan berhasil diperbarui.',
            'data' => $service,
        ]);
    }

    private function ensureAdminAccess(Request $request): void
    {
        /** @var User|null $user */
        $user = $request->user();

        if (! $user || $user->role !== 'admin') {
            throw ValidationException::withMessages([
                'user' => ['Hanya akun admin yang dapat mengelola master layanan.'],
            ]);
        }
    }
}
