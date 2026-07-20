<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PatientAddress;
use App\Models\Product;
use App\Services\JournalService;
use App\Services\PharmacySelectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    public function __construct(
        private readonly PharmacySelectionService $pharmacySelectionService,
        private readonly JournalService $journals
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'patient_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'status' => ['nullable', 'in:pending,confirmed,processed,shipped,delivered,cancelled'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $perPage = $this->resolvePerPage($request);

        $orders = Order::query()
            ->where('patient_user_id', Auth::id())
            ->with(['patient', 'pharmacy.profile', 'pharmacy.owner', 'address', 'prescription', 'items.product', 'shipment.courier'])
            ->when(
                $validated['status'] ?? null,
                fn ($query, $status) => $query->where('status', $status)
            )
            ->latest()
            ->paginate($perPage)
            ->withQueryString();

        return response()->json([
            'message' => 'Daftar order berhasil diambil.',
            'data' => $orders,
        ]);

        if ((int) $validated['patient_user_id'] !== (int) Auth::id()) {
            throw ValidationException::withMessages(['patient_user_id' => ['Order hanya dapat dibuat untuk pasien yang sedang login.']]);
        }

        if (! PatientAddress::query()->whereKey($validated['patient_address_id'])->where('patient_user_id', Auth::id())->exists()) {
            throw ValidationException::withMessages(['patient_address_id' => ['Alamat bukan milik pasien yang sedang login.']]);
        }
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
            $selection['items'] = collect($selection['items'])
                ->sortBy(fn (array $item) => $item['product']->id)
                ->values()
                ->all();

            $subtotal = collect($selection['items'])->sum('total_price');

            $shippingCost = 10000;
            $orderData = [
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
            ];

            if (Schema::hasColumn('orders', 'pharmacy_user_id')) {
                $orderData['pharmacy_user_id'] = $selection['pharmacy']->owner_user_id;
            }

            $order = Order::create($orderData);

            foreach ($selection['items'] as $item) {
                $product = Product::query()->lockForUpdate()->findOrFail($item['product']->id);

                if ($product->track_stock && $product->stock < $item['quantity']) {
                    throw ValidationException::withMessages(['items' => ["Stok {$product->name} tidak mencukupi."]]);
                }

                $unitCost = (string) ($item['product']->cost_price ?? 0);
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'product_name' => $item['product']->name,
                    'unit_price' => $item['unit_price'],
                    'unit_cost' => $unitCost,
                    'quantity' => $item['quantity'],
                    'total_price' => $item['total_price'],
                    'total_cost' => bcmul($unitCost, (string) $item['quantity'], 2),
                ]);

                if ($product->track_stock) {
                    $product->decrement('stock', $item['quantity']);
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
        $this->ensureOrderOwner($order);
        $order->load(['patient', 'pharmacy.profile', 'pharmacy.owner', 'address', 'prescription.items', 'items.product.pharmacy.profile', 'items.product.pharmacy.owner', 'shipment.histories', 'shipment.courier']);

        return response()->json([
            'message' => 'Detail order berhasil diambil.',
            'data' => $order,
        ]);
    }

    public function updateStatus(Request $request, Order $order): JsonResponse
    {
        $this->ensureOrderOwner($order);
        $validated = $request->validate([
            'status' => ['required', 'in:pending,confirmed,processed,shipped,delivered,cancelled'],
            'notes' => ['nullable', 'string'],
        ]);

        $order = DB::transaction(function () use ($order, $validated): Order {
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->id);
            $target = $validated['status'];

            if (in_array($lockedOrder->status, ['delivered', 'cancelled'], true) && $target !== $lockedOrder->status) {
                throw ValidationException::withMessages(['status' => ['Status order final tidak dapat diubah kembali.']]);
            }

            $allowed = [
                'pending' => ['confirmed', 'cancelled'],
                'confirmed' => ['processed', 'cancelled'],
                'processed' => ['shipped', 'cancelled'],
                'shipped' => ['delivered'],
            ];

            if ($target !== $lockedOrder->status && ! in_array($target, $allowed[$lockedOrder->status] ?? [], true)) {
                throw ValidationException::withMessages(['status' => ['Transisi status order tidak diizinkan.']]);
            }

            $lockedOrder->update(['status' => $target, 'notes' => $validated['notes'] ?? $lockedOrder->notes]);
            $this->journals->recordOrderRevenue($lockedOrder->fresh(['items']));

            return $lockedOrder;
        });

        return response()->json([
            'message' => 'Status order berhasil diperbarui.',
            'data' => $order->fresh(['patient', 'pharmacy.profile', 'pharmacy.owner', 'address', 'items.product', 'shipment']),
        ]);
    }

    private function ensureOrderOwner(Order $order): void
    {
        abort_if((int) $order->patient_user_id !== (int) Auth::id(), 403, 'Anda tidak memiliki akses ke order ini.');
    }
}
