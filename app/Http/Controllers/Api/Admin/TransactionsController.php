<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Models\ServiceBooking;
use App\Models\Shipment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TransactionsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $search = $validated['search'] ?? null;

        $payments = Payment::query()
            ->with(['patient', 'consultation'])
            ->when(
                $search,
                fn ($query, $value) => $query->where(function ($paymentQuery) use ($value) {
                    $paymentQuery->where('payment_code', 'like', "%{$value}%")
                        ->orWhereHas('patient', fn ($patientQuery) => $patientQuery->where('name', 'like', "%{$value}%"));
                })
            )
            ->latest()
            ->get();

        $orders = Order::query()
            ->with(['patient', 'pharmacy.profile', 'shipment'])
            ->when(
                $search,
                fn ($query, $value) => $query->where(function ($orderQuery) use ($value) {
                    $orderQuery->where('order_code', 'like', "%{$value}%")
                        ->orWhereHas('patient', fn ($patientQuery) => $patientQuery->where('name', 'like', "%{$value}%"));
                })
            )
            ->latest()
            ->get();

        $serviceBookings = ServiceBooking::query()
            ->with(['service', 'patient', 'assignedPartner'])
            ->when(
                $search,
                fn ($query, $value) => $query->where(function ($bookingQuery) use ($value) {
                    $bookingQuery->where('booking_code', 'like', "%{$value}%")
                        ->orWhereHas('patient', fn ($patientQuery) => $patientQuery->where('name', 'like', "%{$value}%"));
                })
            )
            ->latest()
            ->get();

        $shipments = Shipment::query()
            ->with(['order', 'courier'])
            ->when(
                $search,
                fn ($query, $value) => $query->where(function ($shipmentQuery) use ($value) {
                    $shipmentQuery->where('shipment_code', 'like', "%{$value}%")
                        ->orWhereHas('order', fn ($orderQuery) => $orderQuery->where('order_code', 'like', "%{$value}%"));
                })
            )
            ->latest()
            ->get();

        return response()->json([
            'message' => 'Daftar semua transaksi admin berhasil diambil.',
            'data' => [
                'payments' => $payments,
                'orders' => $orders,
                'service_bookings' => $serviceBookings,
                'shipments' => $shipments,
            ],
        ]);
    }
}
