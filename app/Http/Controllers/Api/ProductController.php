<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['nullable', 'in:obat,produk_kesehatan'],
            'pharmacy_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'requires_prescription' => ['nullable', 'boolean'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $products = Product::query()
            ->where('is_active', true)
            ->with(['pharmacy.partnerProfile'])
            ->when(
                $validated['type'] ?? null,
                fn ($query, $type) => $query->where('type', $type)
            )
            ->when(
                $validated['pharmacy_user_id'] ?? null,
                fn ($query, $pharmacyId) => $query->where('pharmacy_user_id', $pharmacyId)
            )
            ->when(
                array_key_exists('requires_prescription', $validated),
                fn ($query) => $query->where('requires_prescription', $validated['requires_prescription'])
            )
            ->when(
                $validated['search'] ?? null,
                fn ($query, $search) => $query->where(function ($searchQuery) use ($search) {
                    $searchQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('category', 'like', "%{$search}%");
                })
            )
            ->orderBy('name')
            ->get();

        return response()->json([
            'message' => 'Daftar produk berhasil diambil.',
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

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'pharmacy_user_id' => ['required', 'integer', 'exists:users,id'],
            'sku' => ['required', 'string', 'max:100', 'unique:products,sku'],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:obat,produk_kesehatan'],
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

        $pharmacy = User::findOrFail($validated['pharmacy_user_id']);

        if ($pharmacy->role !== 'apotik') {
            throw ValidationException::withMessages([
                'pharmacy_user_id' => ['Produk hanya boleh dimiliki user dengan role apotik.'],
            ]);
        }

        $product = Product::create([
            'pharmacy_user_id' => $validated['pharmacy_user_id'],
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

        $product->load('pharmacy.partnerProfile');

        return response()->json([
            'message' => 'Produk apotik berhasil dibuat.',
            'data' => $product,
        ], 201);
    }

    public function update(Request $request, Product $product): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'type' => ['sometimes', 'in:obat,produk_kesehatan'],
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
        $product->load('pharmacy.partnerProfile');

        return response()->json([
            'message' => 'Produk apotik berhasil diperbarui.',
            'data' => $product,
        ]);
    }

    public function updateStock(Request $request, Product $product): JsonResponse
    {
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
            'data' => $product->fresh('pharmacy.partnerProfile'),
        ]);
    }
}
