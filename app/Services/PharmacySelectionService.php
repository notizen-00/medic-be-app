<?php

namespace App\Services;

use App\Models\PatientAddress;
use App\Models\Pharmacy;
use App\Models\Product;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class PharmacySelectionService
{
    public function getGlobalProductCatalog(array $filters = []): Collection
    {
        $address = $this->resolveAddress($filters['patient_address_id'] ?? null);

        $products = Product::query()
            ->where('is_active', true)
            ->with(['pharmacy.profile', 'pharmacy.owner'])
            ->when(
                $filters['type'] ?? null,
                fn ($query, $type) => $query->where('type', $type)
            )
            ->when(
                array_key_exists('requires_prescription', $filters),
                fn ($query) => $query->where('requires_prescription', $filters['requires_prescription'])
            )
            ->when(
                $filters['search'] ?? null,
                fn ($query, $search) => $query->where(function ($searchQuery) use ($search) {
                    $searchQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('category', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%");
                })
            )
            ->orderBy('name')
            ->get();

        return $products
            ->groupBy('sku')
            ->map(function (Collection $groupedProducts) use ($address) {
                /** @var Product $sample */
                $sample = $groupedProducts->first();
                /** @var Product $cheapest */
                $cheapest = $groupedProducts->sortBy('price')->first();

                $pharmacyOptions = $groupedProducts
                    ->map(function (Product $product) use ($address) {
                        return [
                            'product_id' => $product->id,
                            'pharmacy_id' => $product->pharmacy_id,
                            'owner_user_id' => $product->pharmacy?->owner_user_id,
                            'pharmacy_name' => $product->pharmacy?->name,
                            'price' => $product->price,
                            'stock' => $product->stock,
                            'distance_km' => $this->distanceForAddressAndPharmacy($address, $product->pharmacy),
                            'requires_prescription' => $product->requires_prescription,
                        ];
                    })
                    ->sortBy(fn (array $item) => [
                        $item['distance_km'] ?? PHP_FLOAT_MAX,
                        $item['price'],
                    ])
                    ->values();

                $nearestOption = $pharmacyOptions->first();

                return [
                    'sku' => $sample->sku,
                    'name' => $sample->name,
                    'type' => $sample->type,
                    'category' => $sample->category,
                    'description' => $sample->description,
                    'image' => $sample->image,
                    'requires_prescription' => $sample->requires_prescription,
                    'pharmacy_count' => $groupedProducts->pluck('pharmacy_id')->unique()->count(),
                    'total_stock' => $groupedProducts->sum('stock'),
                    'lowest_price' => $cheapest->price,
                    'highest_price' => $groupedProducts->max('price'),
                    'nearest_pharmacy' => $nearestOption,
                    'pharmacy_options' => $pharmacyOptions,
                ];
            })
            ->sortBy('name')
            ->values();
    }

    public function getProductListGroupedByPharmacy(array $filters = []): Collection
    {
        $address = $this->resolveAddress($filters['patient_address_id'] ?? null);

        $products = Product::query()
            ->where('is_active', true)
            ->with(['pharmacy.profile', 'pharmacy.owner'])
            ->when(
                $filters['type'] ?? null,
                fn ($query, $type) => $query->where('type', $type)
            )
            ->when(
                $filters['pharmacy_id'] ?? null,
                fn ($query, $pharmacyId) => $query->where('pharmacy_id', $pharmacyId)
            )
            ->when(
                array_key_exists('requires_prescription', $filters),
                fn ($query) => $query->where('requires_prescription', $filters['requires_prescription'])
            )
            ->when(
                $filters['search'] ?? null,
                fn ($query, $search) => $query->where(function ($searchQuery) use ($search) {
                    $searchQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('category', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%");
                })
            )
            ->orderBy('name')
            ->get();

        return $products
            ->groupBy('pharmacy_id')
            ->map(function (Collection $groupedProducts) use ($address) {
                $firstProduct = $groupedProducts->first();
                $pharmacy = $firstProduct->pharmacy;

                return [
                    'pharmacy' => $pharmacy,
                    'pharmacy_profile' => $pharmacy?->profile,
                    'owner' => $pharmacy?->owner,
                    'distance_km' => $this->distanceForAddressAndPharmacy($address, $pharmacy),
                    'products' => $groupedProducts->values(),
                ];
            })
            ->sortBy(fn (array $item) => $item['distance_km'] ?? PHP_FLOAT_MAX)
            ->values();
    }

    public function resolveNearestPharmacyForCheckout(int $patientAddressId, array $items, string $orderType): array
    {
        $address = $this->resolveAddress($patientAddressId, true);
        $normalizedItems = $this->normalizeItems($items);
        $skus = collect($normalizedItems)->pluck('sku')->unique()->values()->all();

        $products = Product::query()
            ->whereIn('sku', $skus)
            ->where('is_active', true)
            ->with(['pharmacy.profile', 'pharmacy.owner'])
            ->orderBy('name')
            ->get();

        $candidates = $products
            ->groupBy('pharmacy_id')
            ->map(function (Collection $pharmacyProducts, $pharmacyId) use ($normalizedItems, $address, $orderType) {
                $productsBySku = $pharmacyProducts->keyBy('sku');

                foreach ($normalizedItems as $item) {
                    /** @var Product|null $product */
                    $product = $productsBySku->get($item['sku']);

                    if (! $product) {
                        return null;
                    }

                    if ($product->track_stock && $product->stock < $item['quantity']) {
                        return null;
                    }

                    if ($orderType === 'non_resep' && $product->requires_prescription) {
                        return null;
                    }
                }

                /** @var Product $sampleProduct */
                $sampleProduct = $pharmacyProducts->first();

                return [
                    'pharmacy_id' => (int) $pharmacyId,
                    'pharmacy' => $sampleProduct->pharmacy,
                    'pharmacy_profile' => $sampleProduct->pharmacy?->profile,
                    'owner' => $sampleProduct->pharmacy?->owner,
                    'distance_km' => $this->distanceForAddressAndPharmacy($address, $sampleProduct->pharmacy),
                    'products' => $pharmacyProducts,
                ];
            })
            ->filter()
            ->sortBy(fn (array $item) => $item['distance_km'] ?? PHP_FLOAT_MAX)
            ->values();

        if ($candidates->isEmpty()) {
            throw ValidationException::withMessages([
                'items' => ['Tidak ada apotik terdekat yang memiliki seluruh produk dengan stok yang cukup.'],
            ]);
        }

        $selected = $candidates->first();
        $selectedProductsBySku = $selected['products']->keyBy('sku');

        $resolvedItems = collect($normalizedItems)->map(function (array $item) use ($selectedProductsBySku) {
            /** @var Product $product */
            $product = $selectedProductsBySku->get($item['sku']);
            $totalPrice = (float) $product->price * $item['quantity'];

            return [
                'product' => $product,
                'quantity' => $item['quantity'],
                'total_price' => $totalPrice,
            ];
        })->values();

        return [
            'address' => $address,
            'pharmacy' => $selected['pharmacy'],
            'distance_km' => $selected['distance_km'],
            'items' => $resolvedItems,
        ];
    }

    private function normalizeItems(array $items): array
    {
        $productIds = collect($items)->pluck('product_id')->filter()->values();
        $productsById = $productIds->isEmpty()
            ? new EloquentCollection()
            : Product::query()->whereIn('id', $productIds)->get()->keyBy('id');

        return collect($items)->map(function (array $item) use ($productsById) {
            $sku = $item['sku'] ?? null;

            if (! $sku && ! empty($item['product_id'])) {
                $sku = optional($productsById->get($item['product_id']))->sku;
            }

            if (! $sku) {
                throw ValidationException::withMessages([
                    'items' => ['Setiap item harus memiliki `sku` atau `product_id` yang valid.'],
                ]);
            }

            return [
                'sku' => $sku,
                'quantity' => (int) $item['quantity'],
            ];
        })->all();
    }

    private function resolveAddress(?int $patientAddressId, bool $required = false): ?PatientAddress
    {
        if (! $patientAddressId) {
            if ($required) {
                throw ValidationException::withMessages([
                    'patient_address_id' => ['Alamat pasien dibutuhkan untuk menghitung apotik terdekat.'],
                ]);
            }

            return null;
        }

        $address = PatientAddress::find($patientAddressId);

        if (! $address && $required) {
            throw ValidationException::withMessages([
                'patient_address_id' => ['Alamat pasien tidak ditemukan.'],
            ]);
        }

        return $address;
    }

    private function distanceForAddressAndPharmacy(?PatientAddress $address, ?Pharmacy $pharmacy): ?float
    {
        if (! $address || ! $pharmacy) {
            return null;
        }

        $pharmacyLatitude = $pharmacy->latitude;
        $pharmacyLongitude = $pharmacy->longitude;

        if ($address->latitude === null || $address->longitude === null || $pharmacyLatitude === null || $pharmacyLongitude === null) {
            return null;
        }

        return round(
            $this->calculateHaversineDistance(
                (float) $address->latitude,
                (float) $address->longitude,
                (float) $pharmacyLatitude,
                (float) $pharmacyLongitude
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
