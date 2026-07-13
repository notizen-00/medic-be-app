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
        $applications->getCollection()->each(fn (PartnerService $partnerService) => $this->normalizePartnerVisiblePrice($partnerService));

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
            'price' => ['nullable', 'numeric', 'min:0'],
            'coverage_radius_km' => ['nullable', 'integer', 'min:1'],
            'notes' => ['nullable', 'string'],
        ]);

        $service = Service::with('serviceCategory')->findOrFail($validated['service_id']);
        $this->ensureServiceMatchesProfession($partner->partnerProfile, $service);

        $application = PartnerService::updateOrCreate(
            [
                'service_id' => $service->id,
                'partner_user_id' => $partner->id,
            ],
            [
                'price' => $this->partnerVisiblePriceForService($service, $validated['price'] ?? null),
                'coverage_radius_km' => $validated['coverage_radius_km'] ?? null,
                'is_active' => true,
                'is_available' => true,
                'is_verified' => false,
                'notes' => $validated['notes'] ?? null,
            ]
        );

        $application->load(['service', 'partner.partnerProfile']);
        $this->normalizePartnerVisiblePrice($application);

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
            'price' => ['nullable', 'numeric', 'min:0'],
            'coverage_radius_km' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['sometimes', 'boolean'],
            'is_available' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string'],
        ]);

        $partnerService->loadMissing('service');

        if (array_key_exists('price', $validated) || $partnerService->service) {
            $validated['price'] = $this->partnerVisiblePriceForService(
                $partnerService->service,
                $validated['price'] ?? $partnerService->price
            );
        }

        $partnerService->update($validated);
        $partnerService->load(['service', 'partner.partnerProfile']);
        $this->normalizePartnerVisiblePrice($partnerService);

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
        $this->normalizePartnerVisiblePrice($partnerService);

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

    private function ensureServiceMatchesProfession(PartnerProfile $partnerProfile, Service $service): void
    {
        $categoryKey = strtolower((string) ($service->serviceCategory?->slug ?? $service->serviceCategory?->name ?? ''));

        if ($categoryKey !== '') {
            $allowedCategoryKeywords = match ($partnerProfile->profession) {
                'dokter' => ['doctor', 'dokter'],
                'perawat' => ['nurse', 'perawat', 'caregiver'],
                'bidan' => ['midwife', 'bidan'],
                default => [],
            };

            foreach ($allowedCategoryKeywords as $keyword) {
                if (str_contains($categoryKey, $keyword)) {
                    return;
                }
            }
        }

        $allowedServiceTypes = match ($partnerProfile->profession) {
            'dokter' => ['consultation', 'homecare', 'dokter_homecare', 'konsultasi_tindakan'],
            'perawat' => ['procedure', 'caregiver', 'homecare', 'perawat_homecare', 'konsultasi_tindakan'],
            'bidan' => ['procedure', 'homecare', 'bidan_homecare', 'konsultasi_tindakan'],
            default => [],
        };

        if (! in_array($service->service_type, $allowedServiceTypes, true)) {
            throw ValidationException::withMessages([
                'service_id' => ['Layanan ini tidak sesuai dengan profesi mitra yang sedang login.'],
            ]);
        }
    }

    private function partnerVisiblePriceForService(Service $service, mixed $requestedPrice = null): float
    {
        if ($this->serviceUsesPartnerCustomPrice($service)) {
            return (float) ($requestedPrice ?? $service->base_price ?? 0);
        }

        return (float) ($service->base_price ?? 0);
    }

    private function normalizePartnerVisiblePrice(PartnerService $partnerService): void
    {
        if (! $partnerService->service) {
            return;
        }

        $partnerService->setAttribute('price', $this->partnerVisiblePriceForService(
            $partnerService->service,
            $partnerService->price
        ));
    }

    private function serviceUsesPartnerCustomPrice(Service $service): bool
    {
        return $service->service_type === 'consultation'
            || in_array($service->service_mode, ['chat', 'voice', 'video'], true);
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
