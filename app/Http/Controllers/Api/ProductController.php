<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
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
            'type' => ['nullable', 'in:obat,produk_kesehatan'],
            'patient_address_id' => ['nullable', 'integer', 'exists:patient_addresses,id'],
            'pharmacy_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'requires_prescription' => ['nullable', 'boolean'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $products = $this->pharmacySelectionService->getProductListGroupedByPharmacy($validated);

        return response()->json([
            'message' => 'Daftar produk per apotik berhasil diambil.',
            'data' => $products,
        ]);
    }

    public function global(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['nullable', 'in:obat,produk_kesehatan'],
            'patient_address_id' => ['nullable', 'integer', 'exists:patient_addresses,id'],
            'requires_prescription' => ['nullable', 'boolean'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $products = $this->pharmacySelectionService->getGlobalProductCatalog($validated);

        return response()->json([
            'message' => 'Daftar produk global berhasil diambil.',
            'data' => $products,
        ]);
    }

    public function show(Product $product): JsonResponse
    {
        $product->load('pharmacy.partnerProfile');

        return response()->json([
            'message' => 'Detail produk berhasil diambil.',
            'data' => $product,
        ]);
    }

}
