<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'patient_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'status' => ['nullable', 'in:pending,confirmed,processed,shipped,delivered,cancelled'],
        ]);

        $orders = Order::query()
            ->with(['patient', 'pharmacy.partnerProfile', 'address', 'prescription', 'items.product', 'shipment.courier'])
            ->when(
                $validated['patient_user_id'] ?? null,
                fn ($query, $patientId) => $query->where('patient_user_id', $patientId)
            )
            ->when(
                $validated['status'] ?? null,
                fn ($query, $status) => $query->where('status', $status)
            )
            ->latest()
            ->get();

        return response()->json([
            'message' => 'Daftar order berhasil diambil.',
            'data' => $orders,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'patient_user_id' => ['required', 'integer', 'exists:users,id'],
            'patient_address_id' => ['required', 'integer', 'exists:patient_addresses,id'],
            'prescription_id' => ['nullable', 'integer', 'exists:prescriptions,id'],
            'order_type' => ['required', 'in:resep,non_resep'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ]);

        $order = DB::transaction(function () use ($validated) {
            $subtotal = 0;
            $orderItems = [];
            $pharmacyUserId = null;

            foreach ($validated['items'] as $item) {
                $product = Product::findOrFail($item['product_id']);

                if (! $product->is_active) {
                    throw ValidationException::withMessages([
                        'items' => ["Produk {$product->name} sedang tidak aktif."],
                    ]);
                }

                if ($product->stock < $item['quantity']) {
                    throw ValidationException::withMessages([
                        'items' => ["Stok produk {$product->name} tidak mencukupi."],
                    ]);
                }

                if ($validated['order_type'] === 'non_resep' && $product->requires_prescription) {
                    throw ValidationException::withMessages([
                        'items' => ["Produk {$product->name} membutuhkan resep dokter."],
                    ]);
                }

                if ($pharmacyUserId === null) {
                    $pharmacyUserId = $product->pharmacy_user_id;
                }

                if ($pharmacyUserId !== $product->pharmacy_user_id) {
                    throw ValidationException::withMessages([
                        'items' => ['Semua produk dalam satu order harus berasal dari apotik yang sama.'],
                    ]);
                }

                $lineTotal = $product->price * $item['quantity'];
                $subtotal += $lineTotal;

                $orderItems[] = [
                    'product' => $product,
                    'quantity' => $item['quantity'],
                    'total_price' => $lineTotal,
                ];
            }

            $shippingCost = 10000;
            $order = Order::create([
                'order_code' => 'ORD-' . now()->format('YmdHis') . '-' . str_pad((string) random_int(1, 999), 3, '0', STR_PAD_LEFT),
                'patient_user_id' => $validated['patient_user_id'],
                'pharmacy_user_id' => $pharmacyUserId,
                'patient_address_id' => $validated['patient_address_id'],
                'prescription_id' => $validated['prescription_id'] ?? null,
                'order_type' => $validated['order_type'],
                'status' => 'pending',
                'subtotal' => $subtotal,
                'shipping_cost' => $shippingCost,
                'total_amount' => $subtotal + $shippingCost,
                'notes' => $validated['notes'] ?? null,
                'ordered_at' => now(),
            ]);

            foreach ($orderItems as $item) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item['product']->id,
                    'product_name' => $item['product']->name,
                    'unit_price' => $item['product']->price,
                    'quantity' => $item['quantity'],
                    'total_price' => $item['total_price'],
                ]);

                if ($item['product']->track_stock) {
                    $item['product']->decrement('stock', $item['quantity']);
                }
            }

            return $order;
        });

        $order->load(['patient', 'pharmacy.partnerProfile', 'address', 'items.product']);

        return response()->json([
            'message' => 'Order berhasil dibuat.',
            'data' => $order,
        ], 201);
    }

    public function show(Order $order): JsonResponse
    {
        $order->load(['patient', 'pharmacy.partnerProfile', 'address', 'prescription.items', 'items.product.pharmacy.partnerProfile', 'shipment.histories', 'shipment.courier']);

        return response()->json([
            'message' => 'Detail order berhasil diambil.',
            'data' => $order,
        ]);
    }

    public function updateStatus(Request $request, Order $order): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:pending,confirmed,processed,shipped,delivered,cancelled'],
            'notes' => ['nullable', 'string'],
        ]);

        $order->update([
            'status' => $validated['status'],
            'notes' => $validated['notes'] ?? $order->notes,
        ]);

        return response()->json([
            'message' => 'Status order berhasil diperbarui.',
            'data' => $order->fresh(['patient', 'pharmacy.partnerProfile', 'address', 'items.product', 'shipment']),
        ]);
    }
}
