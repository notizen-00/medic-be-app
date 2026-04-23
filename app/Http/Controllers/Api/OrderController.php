<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\PharmacySelectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function __construct(
        private readonly PharmacySelectionService $pharmacySelectionService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'patient_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'status' => ['nullable', 'in:pending,confirmed,processed,shipped,delivered,cancelled'],
        ]);

        $orders = Order::query()
            ->with(['patient', 'pharmacy.profile', 'pharmacy.owner', 'address', 'prescription', 'items.product', 'shipment.courier'])
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
            'items.*.product_id' => ['nullable', 'integer', 'exists:products,id'],
            'items.*.sku' => ['nullable', 'string', 'max:100'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ]);

        $order = DB::transaction(function () use ($validated) {
            $selection = $this->pharmacySelectionService->resolveNearestPharmacyForCheckout(
                $validated['patient_address_id'],
                $validated['items'],
                $validated['order_type']
            );

            $subtotal = collect($selection['items'])->sum('total_price');

            $shippingCost = 10000;
            $order = Order::create([
                'order_code' => 'ORD-' . now()->format('YmdHis') . '-' . str_pad((string) random_int(1, 999), 3, '0', STR_PAD_LEFT),
                'patient_user_id' => $validated['patient_user_id'],
                'pharmacy_id' => $selection['pharmacy']->id,
                'patient_address_id' => $validated['patient_address_id'],
                'prescription_id' => $validated['prescription_id'] ?? null,
                'order_type' => $validated['order_type'],
                'status' => 'pending',
                'subtotal' => $subtotal,
                'shipping_cost' => $shippingCost,
                'total_amount' => $subtotal + $shippingCost,
                'notes' => trim(($validated['notes'] ?? '') . ' Dipilih otomatis dari apotik terdekat.'),
                'ordered_at' => now(),
            ]);

            foreach ($selection['items'] as $item) {
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

        $order->load(['patient', 'pharmacy.profile', 'pharmacy.owner', 'address', 'items.product']);

        return response()->json([
            'message' => 'Order berhasil dibuat.',
            'data' => $order,
        ], 201);
    }

    public function show(Order $order): JsonResponse
    {
        $order->load(['patient', 'pharmacy.profile', 'pharmacy.owner', 'address', 'prescription.items', 'items.product.pharmacy.profile', 'items.product.pharmacy.owner', 'shipment.histories', 'shipment.courier']);

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
            'data' => $order->fresh(['patient', 'pharmacy.profile', 'pharmacy.owner', 'address', 'items.product', 'shipment']),
        ]);
    }
}
