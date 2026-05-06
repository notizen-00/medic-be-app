<?php

namespace App\Services;

use App\Models\PartnerService;
use App\Models\PatientAddress;
use App\Models\Service;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class ServicePartnerSelectionService
{
    public function getServiceCatalog(array $filters = []): Collection
    {
        $address = $this->resolveAddress($filters['patient_address_id'] ?? null);

        return Service::query()
            ->where('is_active', true)
            ->when(
                $filters['service_type'] ?? null,
                fn ($query, $serviceType) => $query->where('service_type', $serviceType)
            )
            ->when(
                $filters['search'] ?? null,
                fn ($query, $search) => $query->where(function ($searchQuery) use ($search) {
                    $searchQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('category', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                })
            )
            ->with(['partnerServices.partner.partnerProfile'])
            ->orderBy('name')
            ->get()
            ->map(function (Service $service) use ($address) {
                $eligiblePartners = $this->eligiblePartnerServices($service, $address);
                $nearestPartner = $eligiblePartners->first();

                return [
                    'service' => $service,
                    'available_partner_count' => $eligiblePartners->count(),
                    'starting_price' => $eligiblePartners->min('effective_price') ?? $service->base_price,
                    'nearest_partner' => $nearestPartner ? [
                        'partner_service_id' => $nearestPartner->id,
                        'partner_user_id' => $nearestPartner->partner_user_id,
                        'partner_name' => $nearestPartner->partner?->name,
                        'profession' => $nearestPartner->partner?->partnerProfile?->profession,
                        'distance_km' => $nearestPartner->distance_km,
                        'price' => $nearestPartner->effective_price,
                    ] : null,
                ];
            })
            ->values();
    }

    public function resolveNearestPartnerForBooking(Service $service, ?PatientAddress $address): PartnerService
    {
        $eligiblePartners = $this->eligiblePartnerServices($service, $address);

        if ($eligiblePartners->isEmpty()) {
            throw ValidationException::withMessages([
                'service_id' => ['Belum ada mitra aktif yang tersedia untuk layanan ini.'],
            ]);
        }

        /** @var PartnerService $selected */
        $selected = $eligiblePartners->first();

        return $selected;
    }

    private function eligiblePartnerServices(Service $service, ?PatientAddress $address): Collection
    {
        return $service->partnerServices
            ->filter(function (PartnerService $partnerService) use ($address) {
                if (! $partnerService->is_active || ! $partnerService->is_verified) {
                    return false;
                }

                $partnerProfile = $partnerService->partner?->partnerProfile;

                if (! $partnerProfile || $partnerProfile->verification_status !== 'verified' || ! $partnerProfile->is_available) {
                    return false;
                }

                $distanceKm = $this->distanceForAddressAndPartner($address, $partnerService->partner);

                if ($distanceKm !== null && $partnerService->coverage_radius_km !== null && $distanceKm > $partnerService->coverage_radius_km) {
                    return false;
                }

                $partnerService->setAttribute('distance_km', $distanceKm);
                $partnerService->setAttribute('effective_price', $partnerService->custom_price ?? $service->base_price);

                return true;
            })
            ->sortBy(fn (PartnerService $partnerService) => [
                $partnerService->distance_km ?? PHP_FLOAT_MAX,
                $partnerService->effective_price,
            ])
            ->values();
    }

    private function resolveAddress(?int $patientAddressId): ?PatientAddress
    {
        if (! $patientAddressId) {
            return null;
        }

        return PatientAddress::find($patientAddressId);
    }

    private function distanceForAddressAndPartner(?PatientAddress $address, ?User $partner): ?float
    {
        $partnerProfile = $partner?->partnerProfile;

        if (! $address || ! $partnerProfile) {
            return null;
        }

        if ($address->latitude === null || $address->longitude === null || $partnerProfile->latitude === null || $partnerProfile->longitude === null) {
            return null;
        }

        return round(
            $this->calculateHaversineDistance(
                (float) $address->latitude,
                (float) $address->longitude,
                (float) $partnerProfile->latitude,
                (float) $partnerProfile->longitude
            ),
            2
        );
    }

    private function calculateHaversineDistance(
        float $originLatitude,
        float $originLongitude,
        float $destinationLatitude,
        float $destinationLongitude
    ): float {
        $earthRadiusKm = 6371;

        $latitudeDelta = deg2rad($destinationLatitude - $originLatitude);
        $longitudeDelta = deg2rad($destinationLongitude - $originLongitude);

        $a = sin($latitudeDelta / 2) ** 2
            + cos(deg2rad($originLatitude))
            * cos(deg2rad($destinationLatitude))
            * sin($longitudeDelta / 2) ** 2;

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadiusKm * $c;
    }
}
