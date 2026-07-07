<?php

namespace App\Services;

use App\Models\PartnerService;
use App\Models\PatientAddress;
use App\Models\Service;
use App\Models\ServiceBooking;
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
                $bestPartner = $eligiblePartners->first();
                $nearestPartner = $eligiblePartners
                    ->sortBy(fn (PartnerService $partnerService) => $partnerService->distance_km ?? PHP_FLOAT_MAX)
                    ->first();

                return [
                    'service' => $service,
                    'available_partner_count' => $eligiblePartners->count(),
                    'starting_price' => $eligiblePartners->min('effective_price') ?? $service->base_price,
                    'best_partner' => $bestPartner ? [
                        'partner_service_id' => $bestPartner->id,
                        'partner_user_id' => $bestPartner->partner_user_id,
                        'partner_name' => $bestPartner->partner?->name,
                        'profession' => $bestPartner->partner?->partnerProfile?->profession,
                        'distance_km' => $bestPartner->distance_km,
                        'price' => $bestPartner->effective_price,
                        'match_score' => $bestPartner->match_score,
                        'quality_score' => $bestPartner->quality_score,
                    ] : null,
                    'nearest_partner' => $nearestPartner ? [
                        'partner_service_id' => $nearestPartner->id,
                        'partner_user_id' => $nearestPartner->partner_user_id,
                        'partner_name' => $nearestPartner->partner?->name,
                        'profession' => $nearestPartner->partner?->partnerProfile?->profession,
                        'distance_km' => $nearestPartner->distance_km,
                        'price' => $nearestPartner->effective_price,
                        'match_score' => $nearestPartner->match_score,
                        'quality_score' => $nearestPartner->quality_score,
                    ] : null,
                ];
            })
            ->values();
    }

    public function resolveNearestPartnerForBooking(Service $service, ?PatientAddress $address): PartnerService
    {
        return $this->resolveBestPartnerForQuickBooking($service, $address);
    }

    public function resolveBestPartnerForQuickBooking(Service $service, ?PatientAddress $address): PartnerService
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
        $service->loadMissing('partnerServices.partner.partnerProfile');

        $bookingMetrics = $this->bookingMetricsForService($service);
        $maxDistanceKm = max(
            1,
            (float) $service->partnerServices
                ->pluck('coverage_radius_km')
                ->filter()
                ->max() ?: 25
        );
        $maxPrice = max(
            1,
            (float) $service->partnerServices
                ->map(fn (PartnerService $partnerService) => $partnerService->custom_price ?? $service->base_price)
                ->max()
        );

        return $service->partnerServices
            ->filter(function (PartnerService $partnerService) use ($address, $service, $bookingMetrics, $maxDistanceKm, $maxPrice) {
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
                $this->attachMatchScores(
                    $partnerService,
                    $bookingMetrics->get($partnerService->partner_user_id, [
                        'total' => 0,
                        'completed' => 0,
                        'cancelled' => 0,
                    ]),
                    $maxDistanceKm,
                    $maxPrice
                );

                return true;
            })
            ->sortBy(fn (PartnerService $partnerService) => [
                -1 * (float) $partnerService->match_score,
                $partnerService->distance_km ?? PHP_FLOAT_MAX,
                -1 * (float) $partnerService->quality_score,
                (float) $partnerService->effective_price,
            ])
            ->values();
    }

    private function bookingMetricsForService(Service $service): Collection
    {
        $partnerIds = $service->partnerServices
            ->pluck('partner_user_id')
            ->filter()
            ->unique()
            ->values();

        if ($partnerIds->isEmpty()) {
            return collect();
        }

        return ServiceBooking::query()
            ->selectRaw('assigned_partner_user_id')
            ->selectRaw('COUNT(*) as total_bookings')
            ->selectRaw("SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_bookings")
            ->selectRaw("SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_bookings")
            ->where('service_id', $service->id)
            ->whereIn('assigned_partner_user_id', $partnerIds)
            ->groupBy('assigned_partner_user_id')
            ->get()
            ->mapWithKeys(fn ($metric) => [
                (int) $metric->assigned_partner_user_id => [
                    'total' => (int) $metric->total_bookings,
                    'completed' => (int) $metric->completed_bookings,
                    'cancelled' => (int) $metric->cancelled_bookings,
                ],
            ]);
    }

    private function attachMatchScores(
        PartnerService $partnerService,
        array $bookingMetric,
        float $maxDistanceKm,
        float $maxPrice
    ): void {
        $distanceScore = $partnerService->distance_km === null
            ? 60
            : max(0, 100 - (((float) $partnerService->distance_km / $maxDistanceKm) * 100));

        $experienceYears = (int) ($partnerService->partner?->partnerProfile?->years_of_experience ?? 0);
        $experienceScore = min(100, $experienceYears * 10);

        $totalBookings = max(0, (int) $bookingMetric['total']);
        $completedBookings = max(0, (int) $bookingMetric['completed']);
        $cancelledBookings = max(0, (int) $bookingMetric['cancelled']);
        $completionRate = $totalBookings > 0 ? $completedBookings / $totalBookings : 0.75;
        $completionVolumeScore = min(100, $completedBookings * 10);
        $cancellationPenalty = min(30, $cancelledBookings * 5);

        $qualityScore = max(
            0,
            min(100, ($experienceScore * 0.45) + (($completionRate * 100) * 0.35) + ($completionVolumeScore * 0.20) - $cancellationPenalty)
        );

        $priceScore = max(0, 100 - (((float) $partnerService->effective_price / $maxPrice) * 100));
        $matchScore = ($distanceScore * 0.50) + ($qualityScore * 0.40) + ($priceScore * 0.10);

        $partnerService->setAttribute('distance_score', round($distanceScore, 2));
        $partnerService->setAttribute('quality_score', round($qualityScore, 2));
        $partnerService->setAttribute('price_score', round($priceScore, 2));
        $partnerService->setAttribute('match_score', round($matchScore, 2));
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
