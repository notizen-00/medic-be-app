<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Pharmacy;
use App\Models\Product;
use App\Services\PharmacySelectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function __construct(
        private readonly PharmacySelectionService $pharmacySelectionService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['nullable', 'in:obat,produk_kesehatan,layanan,sewa_alat_kesehatan'],
            'patient_address_id' => ['nullable', 'integer', 'exists:patient_addresses,id'],
            'pharmacy_id' => ['nullable', 'integer', 'exists:pharmacies,id'],
            'pharmacy_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'requires_prescription' => ['nullable', 'boolean'],
            'search' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $perPage = $this->resolvePerPage($request);

        if (! isset($validated['pharmacy_id']) && isset($validated['pharmacy_user_id'])) {
            $validated['pharmacy_id'] = Pharmacy::query()
                ->where('owner_user_id', $validated['pharmacy_user_id'])
                ->value('id');
        }

        $products = $this->paginateCollection(
            $this->pharmacySelectionService->getProductListGroupedByPharmacy($validated),
            $request,
            $perPage
        );

        return response()->json([
            'message' => 'Daftar produk per apotik berhasil diambil.',
            'data' => $products,
        ]);
    }

    public function global(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['nullable', 'in:obat,produk_kesehatan,layanan,sewa_alat_kesehatan'],
            'patient_address_id' => ['nullable', 'integer', 'exists:patient_addresses,id'],
            'requires_prescription' => ['nullable', 'boolean'],
            'search' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $perPage = $this->resolvePerPage($request);

        $products = $this->paginateCollection(
            $this->pharmacySelectionService->getGlobalProductCatalog($validated),
            $request,
            $perPage
        );

        return response()->json([
            'message' => 'Daftar produk global berhasil diambil.',
            'data' => $products,
        ]);
    }

    public function show(Product $product): JsonResponse
    {
        $product->load(['pharmacy.profile', 'pharmacy.owner']);

        return response()->json([
            'message' => 'Detail produk berhasil diambil.',
            'data' => $product,
        ]);
    }

}
