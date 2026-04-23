<?php

namespace App\Http\Controllers\Api\Apotik;

use App\Http\Controllers\Controller;
use App\Models\Pharmacy;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $pharmacy = $this->resolveAuthenticatedApotik($request);

        $validated = $request->validate([
            'type' => ['nullable', 'in:obat,produk_kesehatan,layanan,sewa_alat_kesehatan'],
            'requires_prescription' => ['nullable', 'boolean'],
            'search' => ['nullable', 'string', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $products = Product::query()
            ->where('pharmacy_id', $pharmacy->id)
            ->when(
                $validated['type'] ?? null,
                fn ($query, $type) => $query->where('type', $type)
            )
            ->when(
                array_key_exists('requires_prescription', $validated),
                fn ($query) => $query->where('requires_prescription', $validated['requires_prescription'])
            )
            ->when(
                array_key_exists('is_active', $validated),
                fn ($query) => $query->where('is_active', $validated['is_active'])
            )
            ->when(
                $validated['search'] ?? null,
                fn ($query, $search) => $query->where('name', 'like', "%{$search}%")
            )
            ->with(['pharmacy.profile', 'pharmacy.owner'])
            ->latest()
            ->get();

        return response()->json([
            'message' => 'Daftar produk apotik berhasil diambil.',
            'data' => $products,
        ]);
    }

    public function show(Request $request, Product $product): JsonResponse
    {
        $this->ensureProductOwnedByAuthenticatedApotik($request, $product);

        $product->load(['pharmacy.profile', 'pharmacy.owner']);

        return response()->json([
            'message' => 'Detail produk apotik berhasil diambil.',
            'data' => $product,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $pharmacy = $this->resolveAuthenticatedApotik($request);

        $validated = $request->validate([
            'sku' => [
                'required',
                'string',
                'max:100',
                Rule::unique('products')->where(fn ($query) => $query->where('pharmacy_id', $pharmacy->id)),
            ],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:obat,produk_kesehatan,layanan,sewa_alat_kesehatan'],
            'category' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0'],
            'stock' => ['nullable', 'integer', 'min:0'],
            'minimum_stock_alert' => ['nullable', 'integer', 'min:0'],
            'track_stock' => ['nullable', 'boolean'],
            'requires_prescription' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'image' => ['nullable', 'string', 'max:255'],
        ]);

        $product = Product::create([
            'pharmacy_id' => $pharmacy->id,
            'sku' => $validated['sku'],
            'name' => $validated['name'],
            'type' => $validated['type'],
            'category' => $validated['category'] ?? null,
            'description' => $validated['description'] ?? null,
            'price' => $validated['price'],
            'stock' => $validated['stock'] ?? 0,
            'minimum_stock_alert' => $validated['minimum_stock_alert'] ?? 5,
            'track_stock' => $validated['track_stock'] ?? true,
            'requires_prescription' => $validated['requires_prescription'] ?? false,
            'is_active' => $validated['is_active'] ?? true,
            'image' => $validated['image'] ?? null,
        ]);

        $product->load(['pharmacy.profile', 'pharmacy.owner']);

        return response()->json([
            'message' => 'Produk apotik berhasil dibuat.',
            'data' => $product,
        ], 201);
    }

    public function update(Request $request, Product $product): JsonResponse
    {
        $this->ensureProductOwnedByAuthenticatedApotik($request, $product);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'type' => ['sometimes', 'in:obat,produk_kesehatan,layanan,sewa_alat_kesehatan'],
            'category' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'price' => ['sometimes', 'numeric', 'min:0'],
            'minimum_stock_alert' => ['sometimes', 'integer', 'min:0'],
            'track_stock' => ['sometimes', 'boolean'],
            'requires_prescription' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'image' => ['nullable', 'string', 'max:255'],
        ]);

        $product->update($validated);
        $product->load(['pharmacy.profile', 'pharmacy.owner']);

        return response()->json([
            'message' => 'Produk apotik berhasil diperbarui.',
            'data' => $product,
        ]);
    }

    public function updateStock(Request $request, Product $product): JsonResponse
    {
        $this->ensureProductOwnedByAuthenticatedApotik($request, $product);

        $validated = $request->validate([
            'stock' => ['required', 'integer', 'min:0'],
            'minimum_stock_alert' => ['nullable', 'integer', 'min:0'],
            'track_stock' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $product->update([
            'stock' => $validated['stock'],
            'minimum_stock_alert' => $validated['minimum_stock_alert'] ?? $product->minimum_stock_alert,
            'track_stock' => $validated['track_stock'] ?? $product->track_stock,
            'is_active' => $validated['is_active'] ?? $product->is_active,
        ]);

        return response()->json([
            'message' => 'Stok produk apotik berhasil diperbarui.',
            'data' => $product->fresh(['pharmacy.profile', 'pharmacy.owner']),
        ]);
    }

    public function destroy(Request $request, Product $product): JsonResponse
    {
        $this->ensureProductOwnedByAuthenticatedApotik($request, $product);

        $product->delete();

        return response()->json([
            'message' => 'Produk apotik berhasil dihapus.',
        ], Response::HTTP_OK);
    }

    private function resolveAuthenticatedApotik(Request $request): Pharmacy
    {
        /** @var User|null $user */
        $user = $request->user();

        if (! $user || $user->role !== 'mitra' || ! $user->pharmacy) {
            throw ValidationException::withMessages([
                'user' => ['Akun login harus mitra yang memiliki data apotik.'],
            ]);
        }

        return $user->pharmacy;
    }

    private function ensureProductOwnedByAuthenticatedApotik(Request $request, Product $product): void
    {
        $pharmacy = $this->resolveAuthenticatedApotik($request);

        if ($product->pharmacy_id !== $pharmacy->id) {
            throw ValidationException::withMessages([
                'product' => ['Produk ini bukan milik apotik yang sedang login.'],
            ]);
        }
    }
}
