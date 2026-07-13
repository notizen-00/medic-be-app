<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Models\ServiceBooking;
use App\Models\Shipment;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

class TransactionsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $perPage = $this->resolvePerPage($request, 100);
        $page = max(1, (int) $request->input('page', 1));
        $search = $validated['search'] ?? null;

        $payments = Payment::query()
            ->with(['patient', 'consultation', 'serviceBooking.service'])
            ->when(
                $search,
                fn ($query, $value) => $query->where(function ($paymentQuery) use ($value) {
                    $paymentQuery->where('payment_code', 'like', "%{$value}%")
                        ->orWhereHas('patient', fn ($patientQuery) => $patientQuery->where('name', 'like', "%{$value}%"));
                })
            )
            ->latest()
            ->limit(300)
            ->get()
            ->map(fn (Payment $payment) => [
                'id' => 'payment-'.$payment->id,
                'transaction_code' => $payment->payment_code,
                'reference' => $payment->payment_code,
                'reference_type' => $payment->service_booking_id ? 'service_booking_payment' : ($payment->consultation_id ? 'consultation_payment' : 'payment'),
                'reference_id' => $payment->service_booking_id ?? $payment->consultation_id ?? $payment->id,
                'type' => 'payment',
                'transaction_type' => 'payment',
                'amount' => (float) $payment->amount,
                'value' => (float) $payment->amount,
                'status' => $payment->status,
                'description' => $payment->notes ?? 'Pembayaran',
                'notes' => $payment->notes,
                'created_at' => $payment->created_at?->toISOString(),
                'transaction_date' => $payment->created_at?->toISOString(),
                'user' => $payment->patient,
                'patient' => $payment->patient,
            ]);

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
            ->limit(300)
            ->get()
            ->map(fn (Order $order) => [
                'id' => 'order-'.$order->id,
                'transaction_code' => $order->order_code,
                'reference' => $order->order_code,
                'reference_type' => 'order',
                'reference_id' => $order->id,
                'type' => 'order',
                'transaction_type' => 'order',
                'amount' => (float) $order->total_amount,
                'total_amount' => (float) $order->total_amount,
                'value' => (float) $order->total_amount,
                'status' => $order->status,
                'description' => $order->notes ?? 'Order produk',
                'notes' => $order->notes,
                'created_at' => ($order->ordered_at ?? $order->created_at)?->toISOString(),
                'transaction_date' => ($order->ordered_at ?? $order->created_at)?->toISOString(),
                'user' => $order->patient,
                'patient' => $order->patient,
                'partner' => $order->pharmacy,
            ]);

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
            ->limit(300)
            ->get()
            ->map(fn (ServiceBooking $booking) => [
                'id' => 'service-booking-'.$booking->id,
                'transaction_code' => $booking->booking_code,
                'reference' => $booking->booking_code,
                'reference_type' => 'service_booking',
                'reference_id' => $booking->id,
                'type' => 'service_booking',
                'transaction_type' => 'service_booking',
                'amount' => (float) $booking->total_amount,
                'total_amount' => (float) $booking->total_amount,
                'value' => (float) $booking->total_amount,
                'status' => $booking->status,
                'description' => $booking->notes ?? $booking->service?->name ?? 'Service booking',
                'notes' => $booking->notes,
                'created_at' => $booking->created_at?->toISOString(),
                'transaction_date' => $booking->created_at?->toISOString(),
                'user' => $booking->patient,
                'patient' => $booking->patient,
                'partner' => $booking->assignedPartner,
            ]);

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
            ->limit(300)
            ->get()
            ->map(fn (Shipment $shipment) => [
                'id' => 'shipment-'.$shipment->id,
                'transaction_code' => $shipment->shipment_code,
                'reference' => $shipment->shipment_code,
                'reference_type' => 'shipment',
                'reference_id' => $shipment->id,
                'type' => 'shipment',
                'transaction_type' => 'shipment',
                'amount' => 0,
                'value' => 0,
                'status' => $shipment->status,
                'description' => $shipment->notes ?? 'Shipment',
                'notes' => $shipment->notes,
                'created_at' => $shipment->created_at?->toISOString(),
                'transaction_date' => $shipment->created_at?->toISOString(),
                'user' => $shipment->courier,
                'partner' => $shipment->courier,
            ]);

        $items = $payments
            ->concat($orders)
            ->concat($serviceBookings)
            ->concat($shipments)
            ->sortByDesc('created_at')
            ->values();

        $paginated = new LengthAwarePaginator(
            $items->forPage($page, $perPage)->values(),
            $items->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return response()->json([
            'message' => 'OK',
            'data' => $paginated,
        ]);
    }
}
