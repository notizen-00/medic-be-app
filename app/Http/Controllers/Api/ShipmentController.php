<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Shipment;
use App\Models\ShipmentHistory;
use App\Models\User;
use App\Services\JournalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ShipmentController extends Controller
{
    public function __construct(
        private readonly JournalService $journals
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'courier_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'status' => ['nullable', 'in:waiting_courier,picked_up,on_delivery,delivered,failed,cancelled'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $perPage = $this->resolvePerPage($request);

        $shipments = Shipment::query()
            ->with(['order.patient', 'order.address', 'courier.courierProfile', 'histories'])
            ->when(
                $validated['courier_user_id'] ?? null,
                fn ($query, $courierId) => $query->where('courier_user_id', $courierId)
            )
            ->when(
                $validated['status'] ?? null,
                fn ($query, $status) => $query->where('status', $status)
            )
            ->latest()
            ->paginate($perPage)
            ->withQueryString();

        return response()->json([
            'message' => 'Daftar shipment berhasil diambil.',
            'data' => $shipments,
        ]);
    }

    public function show(Shipment $shipment): JsonResponse
    {
        $shipment->load(['order.patient', 'order.address', 'order.items.product', 'courier.courierProfile', 'histories']);

        return response()->json([
            'message' => 'Detail shipment berhasil diambil.',
            'data' => $shipment,
        ]);
    }

    public function assignCourier(Request $request, Shipment $shipment): JsonResponse
    {
        $validated = $request->validate([
            'courier_user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $courier = User::with('courierProfile')->findOrFail($validated['courier_user_id']);

        if ($courier->role !== 'mitra' || ! $courier->courierProfile) {
            throw ValidationException::withMessages([
                'courier_user_id' => ['User yang dipilih bukan mitra kurir yang valid.'],
            ]);
        }

        $shipment->update([
            'courier_user_id' => $courier->id,
            'status' => 'waiting_courier',
            'assigned_at' => now(),
        ]);

        ShipmentHistory::create([
            'shipment_id' => $shipment->id,
            'status' => 'waiting_courier',
            'title' => 'Kurir ditugaskan',
            'description' => "Kurir {$courier->name} ditugaskan untuk pengiriman.",
            'logged_at' => now(),
        ]);

        return response()->json([
            'message' => 'Kurir berhasil ditugaskan.',
            'data' => $shipment->fresh(['courier.courierProfile', 'histories']),
        ]);
    }

    public function updateStatus(Request $request, Shipment $shipment): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:waiting_courier,picked_up,on_delivery,delivered,failed,cancelled'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        $payload = [
            'status' => $validated['status'],
        ];

        if ($validated['status'] === 'picked_up' && $shipment->picked_up_at === null) {
            $payload['picked_up_at'] = now();
        }

        if ($validated['status'] === 'delivered' && $shipment->delivered_at === null) {
            $payload['delivered_at'] = now();
        }

        $shipment->update($payload);

        ShipmentHistory::create([
            'shipment_id' => $shipment->id,
            'status' => $validated['status'],
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'logged_at' => now(),
        ]);

        if ($validated['status'] === 'delivered') {
            $shipment->order()->update(['status' => 'delivered']);
            $this->journals->recordOrderRevenue($shipment->order()->with('items')->first());
        }

        if (in_array($validated['status'], ['picked_up', 'on_delivery'], true)) {
            $shipment->order()->update(['status' => 'shipped']);
        }

        return response()->json([
            'message' => 'Status shipment berhasil diperbarui.',
            'data' => $shipment->fresh(['order', 'courier.courierProfile', 'histories']),
        ]);
    }
}
