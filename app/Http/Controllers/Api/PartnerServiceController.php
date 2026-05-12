<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PartnerProfile;
use App\Models\PartnerService;
use App\Models\Service;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PartnerServiceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $partner = $this->resolveAuthenticatedMedicalPartner($request);

        $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $perPage = $this->resolvePerPage($request);

        $applications = PartnerService::query()
            ->with(['service', 'partner.partnerProfile'])
            ->where('partner_user_id', $partner->id)
            ->latest()
            ->paginate($perPage)
            ->withQueryString();

        return response()->json([
            'message' => 'Daftar layanan mitra berhasil diambil.',
            'data' => $applications,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $partner = $this->resolveAuthenticatedMedicalPartner($request);

        $validated = $request->validate([
            'service_id' => ['required', 'integer', 'exists:services,id'],
            'custom_price' => ['nullable', 'numeric', 'min:0'],
            'coverage_radius_km' => ['nullable', 'integer', 'min:1'],
            'notes' => ['nullable', 'string'],
        ]);

        $service = Service::findOrFail($validated['service_id']);
        $this->ensureServiceTypeMatchesProfession($partner->partnerProfile, $service->service_type);

        $application = PartnerService::updateOrCreate(
            [
                'service_id' => $service->id,
                'partner_user_id' => $partner->id,
            ],
            [
                'custom_price' => $validated['custom_price'] ?? null,
                'coverage_radius_km' => $validated['coverage_radius_km'] ?? null,
                'is_active' => true,
                'is_verified' => false,
                'notes' => $validated['notes'] ?? null,
            ]
        );

        $application->load(['service', 'partner.partnerProfile']);

        return response()->json([
            'message' => 'Pengajuan layanan mitra berhasil dibuat dan menunggu verifikasi admin.',
            'data' => $application,
        ], 201);
    }

    public function update(Request $request, PartnerService $partnerService): JsonResponse
    {
        $partner = $this->resolveAuthenticatedMedicalPartner($request);

        if ($partnerService->partner_user_id !== $partner->id) {
            throw ValidationException::withMessages([
                'partner_service' => ['Layanan mitra ini bukan milik akun yang sedang login.'],
            ]);
        }

        $validated = $request->validate([
            'custom_price' => ['nullable', 'numeric', 'min:0'],
            'coverage_radius_km' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string'],
        ]);

        $partnerService->update($validated);
        $partnerService->load(['service', 'partner.partnerProfile']);

        return response()->json([
            'message' => 'Layanan mitra berhasil diperbarui.',
            'data' => $partnerService,
        ]);
    }

    public function verify(Request $request, PartnerService $partnerService): JsonResponse
    {
        $this->ensureAdminAccess($request);

        $validated = $request->validate([
            'is_verified' => ['required', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string'],
        ]);

        $partnerService->update([
            'is_verified' => $validated['is_verified'],
            'is_active' => $validated['is_active'] ?? $partnerService->is_active,
            'notes' => $validated['notes'] ?? $partnerService->notes,
        ]);

        $partnerService->load(['service', 'partner.partnerProfile']);

        return response()->json([
            'message' => 'Status verifikasi layanan mitra berhasil diperbarui.',
            'data' => $partnerService,
        ]);
    }

    private function resolveAuthenticatedMedicalPartner(Request $request): User
    {
        /** @var User|null $user */
        $user = $request->user();

        if (! $user) {
            throw ValidationException::withMessages([
                'user' => ['User login tidak ditemukan.'],
            ]);
        }

        $user->loadMissing('partnerProfile');

        if (! $user->partnerProfile || $user->role !== 'mitra') {
            throw ValidationException::withMessages([
                'user' => ['Akun login harus mitra layanan kesehatan.'],
            ]);
        }

        return $user;
    }

    private function ensureServiceTypeMatchesProfession(PartnerProfile $partnerProfile, string $serviceType): void
    {
        $allowedServiceTypes = match ($partnerProfile->profession) {
            'dokter' => ['dokter_homecare', 'konsultasi_tindakan'],
            'perawat' => ['perawat_homecare', 'konsultasi_tindakan'],
            'bidan' => ['bidan_homecare', 'konsultasi_tindakan'],
            default => [],
        };

        if (! in_array($serviceType, $allowedServiceTypes, true)) {
            throw ValidationException::withMessages([
                'service_id' => ['Layanan ini tidak sesuai dengan profesi mitra yang sedang login.'],
            ]);
        }
    }

    private function ensureAdminAccess(Request $request): void
    {
        /** @var User|null $user */
        $user = $request->user();

        if (! $user || $user->role !== 'admin') {
            throw ValidationException::withMessages([
                'user' => ['Hanya akun admin yang dapat memverifikasi layanan mitra.'],
            ]);
        }
    }
}
