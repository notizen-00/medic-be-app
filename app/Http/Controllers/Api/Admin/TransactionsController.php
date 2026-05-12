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
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $perPage = $this->resolvePerPage($request);

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
            ->paginate($perPage, ['*'], 'payments_page')
            ->withQueryString();

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
            ->paginate($perPage, ['*'], 'orders_page')
            ->withQueryString();

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
            ->paginate($perPage, ['*'], 'service_bookings_page')
            ->withQueryString();

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
            ->paginate($perPage, ['*'], 'shipments_page')
            ->withQueryString();

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
