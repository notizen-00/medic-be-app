<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductCatalogController extends Controller
{
    public function updateAdminPrice(Request $request, Product $product): JsonResponse
    {
        $validated = $request->validate([
            'admin_price' => ['required', 'numeric', 'min:0'],
        ]);

        Product::query()
            ->where('sku', $product->sku)
            ->update(['admin_price' => $validated['admin_price']]);

        return response()->json([
            'message' => 'Harga katalog admin berhasil diperbarui untuk semua produk dengan SKU yang sama.',
            'data' => [
                'sku' => $product->sku,
                'admin_price' => number_format((float) $validated['admin_price'], 2, '.', ''),
                'updated_count' => Product::query()->where('sku', $product->sku)->count(),
            ],
        ]);
    }
}
