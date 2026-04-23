<?php

namespace App\Services;

use App\Models\PatientAddress;
use App\Models\User;
use Illuminate\Support\Collection;

class DoctorDirectoryService
{
    public function getPartnerList(string $profession, array $filters = []): Collection
    {
        $referencePoint = $this->resolveReferencePoint(
            $filters['patient_address_id'] ?? null,
            $filters['latitude'] ?? null,
            $filters['longitude'] ?? null
        );

        return User::query()
            ->whereHas('partnerProfile', function ($query) use ($profession, $filters) {
                $query->where('profession', $profession)
                    ->when(
                        $filters['specialization'] ?? null,
                        fn ($profileQuery, $specialization) => $profileQuery->where('specialization', 'like', "%{$specialization}%")
                    )
                    ->when(
                        array_key_exists('is_available', $filters),
                        fn ($profileQuery) => $profileQuery->where('is_available', $filters['is_available'])
                    );
            })
            ->when(
                $filters['search'] ?? null,
                fn ($query, $search) => $query->where(function ($searchQuery) use ($search) {
                    $searchQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhereHas('partnerProfile', fn ($profileQuery) => $profileQuery
                            ->where('specialization', 'like', "%{$search}%")
                            ->orWhere('work_location', 'like', "%{$search}%")
                            ->orWhere('bio', 'like', "%{$search}%"));
                })
            )
            ->with('partnerProfile')
            ->get()
            ->map(function (User $doctor) use ($referencePoint) {
                $partnerProfile = $doctor->partnerProfile;

                $doctor->setAttribute(
                    'distance_km',
                    $this->distanceForPartnerProfile($partnerProfile, $referencePoint)
                );

                return $doctor;
            })
            ->when(
                isset($filters['max_distance_km']),
                fn (Collection $doctors) => $doctors->filter(function (User $doctor) use ($filters) {
                    $distanceKm = $doctor->getAttribute('distance_km');

                    return $distanceKm !== null && $distanceKm <= (float) $filters['max_distance_km'];
                })
            )
            ->sortBy(fn (User $doctor) => [
                $doctor->getAttribute('distance_km') ?? PHP_FLOAT_MAX,
                $doctor->partnerProfile?->is_available ? 0 : 1,
                $doctor->name,
            ])
            ->when(
                isset($filters['limit']),
                fn (Collection $doctors) => $doctors->take((int) $filters['limit'])
            )
            ->values();
    }

    public function getDoctorList(array $filters = []): Collection
    {
        return $this->getPartnerList('dokter', $filters);
    }

    public function getNurseList(array $filters = []): Collection
    {
        return $this->getPartnerList('perawat', $filters);
    }

    private function resolveReferencePoint(?int $patientAddressId, ?float $latitude, ?float $longitude): ?array
    {
        if ($latitude !== null && $longitude !== null) {
            return [
                'latitude' => $latitude,
                'longitude' => $longitude,
            ];
        }

        if (! $patientAddressId) {
            return null;
        }

        $address = PatientAddress::find($patientAddressId);

        if (! $address || $address->latitude === null || $address->longitude === null) {
            return null;
        }

        return [
            'latitude' => (float) $address->latitude,
            'longitude' => (float) $address->longitude,
        ];
    }

    private function distanceForPartnerProfile($partnerProfile, ?array $referencePoint): ?float
    {
        if (! $partnerProfile || ! $referencePoint) {
            return null;
        }

        if ($partnerProfile->latitude === null || $partnerProfile->longitude === null) {
            return null;
        }

        return round(
            $this->calculateHaversineDistance(
                (float) $referencePoint['latitude'],
                (float) $referencePoint['longitude'],
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
