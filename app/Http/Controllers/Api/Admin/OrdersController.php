<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrdersController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', 'in:pending,confirmed,processed,shipped,delivered,cancelled'],
            'order_type' => ['nullable', 'in:resep,non_resep'],
            'patient_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'pharmacy_id' => ['nullable', 'integer', 'exists:pharmacies,id'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $orders = Order::query()
            ->with(['patient', 'pharmacy.profile', 'pharmacy.owner', 'address', 'prescription', 'items.product', 'shipment.courier'])
            ->when(
                $validated['status'] ?? null,
                fn ($query, $status) => $query->where('status', $status)
            )
            ->when(
                $validated['order_type'] ?? null,
                fn ($query, $orderType) => $query->where('order_type', $orderType)
            )
            ->when(
                $validated['patient_user_id'] ?? null,
                fn ($query, $patientUserId) => $query->where('patient_user_id', $patientUserId)
            )
            ->when(
                $validated['pharmacy_id'] ?? null,
                fn ($query, $pharmacyId) => $query->where('pharmacy_id', $pharmacyId)
            )
            ->when(
                $validated['search'] ?? null,
                fn ($query, $search) => $query->where(function ($searchQuery) use ($search) {
                    $searchQuery->where('order_code', 'like', "%{$search}%")
                        ->orWhere('notes', 'like', "%{$search}%")
                        ->orWhereHas('patient', fn ($patientQuery) => $patientQuery
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%"))
                        ->orWhereHas('pharmacy.profile', fn ($pharmacyQuery) => $pharmacyQuery
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('license_number', 'like', "%{$search}%"));
                })
            )
            ->latest()
            ->get();

        return response()->json([
            'message' => 'Daftar semua order admin berhasil diambil.',
            'data' => $orders,
        ]);
    }

    public function show(Request $request, Order $order): JsonResponse
    {
        $order->load(['patient', 'pharmacy.profile', 'pharmacy.owner', 'address', 'prescription.items', 'items.product.pharmacy.profile', 'shipment.histories', 'shipment.courier']);

        return response()->json([
            'message' => 'Detail order admin berhasil diambil.',
            'data' => $order,
        ]);
    }
}
