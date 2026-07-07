<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\ServiceBooking;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ReportsController extends Controller
{
    /**
     * Report: orders (detail & filter)
     */
    public function orders(Request $request)
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'status' => ['nullable', Rule::in(['pending', 'confirmed', 'processed', 'shipped', 'delivered', 'cancelled'])],
            'patient_user_id' => ['nullable', 'integer', 'min:1'],
            'pharmacy_id' => ['nullable', 'integer', 'min:1'],
        ]);

        $query = Order::query()
            ->with(['patient:id,name,email', 'pharmacy:id,name', 'items:id,order_id,product_name,unit_price,quantity,total_price'])
            ->latest('ordered_at')
            ->latest('id');

        if (!empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }
        if (!empty($validated['patient_user_id'])) {
            $query->where('patient_user_id', $validated['patient_user_id']);
        }
        if (!empty($validated['pharmacy_id'])) {
            $query->where('pharmacy_id', $validated['pharmacy_id']);
        }
        if (!empty($validated['from'])) {
            $query->whereDate('ordered_at', '>=', $validated['from']);
        }
        if (!empty($validated['to'])) {
            $query->whereDate('ordered_at', '<=', $validated['to']);
        }

        return response()->json([
            'data' => $query->paginate(20),
        ]);
    }

    /**
     * Report: customers summary (orders per customer)
     */
    public function customers(Request $request)
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $orders = Order::query();
        if (!empty($validated['from'])) {
            $orders->whereDate('ordered_at', '>=', $validated['from']);
        }
        if (!empty($validated['to'])) {
            $orders->whereDate('ordered_at', '<=', $validated['to']);
        }

        $rows = $orders
            ->select([
                'patient_user_id',
                DB::raw('COUNT(*) as total_orders'),
                DB::raw('SUM(total_amount) as total_spent'),
                DB::raw('MAX(ordered_at) as last_order_at'),
            ])
            ->groupBy('patient_user_id')
            ->orderByDesc('total_spent')
            ->paginate(20);

        $userIds = collect($rows->items())->pluck('patient_user_id')->all();
        $users = User::query()
            ->whereIn('id', $userIds)
            ->get(['id', 'name', 'email'])
            ->keyBy('id');

        $rows->getCollection()->transform(function ($row) use ($users) {
            $row->customer = $users->get($row->patient_user_id);
            return $row;
        });

        return response()->json(['data' => $rows]);
    }

    /**
     * Report: profit & loss (basic)
     *
     * Notes:
     * - Orders COGS is calculated from order_items cost snapshot (unit_cost/total_cost).
     * - Service bookings include markup_amount & discount_amount; we treat markup_amount as "platform revenue".
     */
    public function profitLoss(Request $request)
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $orders = Order::query()->where('status', 'delivered');
        if (!empty($validated['from'])) {
            $orders->whereDate('ordered_at', '>=', $validated['from']);
        }
        if (!empty($validated['to'])) {
            $orders->whereDate('ordered_at', '<=', $validated['to']);
        }

        $serviceBookings = ServiceBooking::query()->where('status', 'completed');
        if (!empty($validated['from'])) {
            $serviceBookings->whereDate('completed_at', '>=', $validated['from']);
        }
        if (!empty($validated['to'])) {
            $serviceBookings->whereDate('completed_at', '<=', $validated['to']);
        }

        $ordersRevenue = (float) ($orders->sum('total_amount') ?? 0);
        $ordersSubtotal = (float) ($orders->sum('subtotal') ?? 0);
        $ordersShipping = (float) ($orders->sum('shipping_cost') ?? 0);

        $ordersCogsQuery = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.status', 'delivered');
        if (!empty($validated['from'])) {
            $ordersCogsQuery->whereDate('orders.ordered_at', '>=', $validated['from']);
        }
        if (!empty($validated['to'])) {
            $ordersCogsQuery->whereDate('orders.ordered_at', '<=', $validated['to']);
        }
        $ordersCogs = (float) ($ordersCogsQuery->sum('order_items.total_cost') ?? 0);

        $servicesRevenue = (float) ($serviceBookings->sum('total_amount') ?? 0);
        $servicesMarkup = (float) ($serviceBookings->sum('markup_amount') ?? 0);
        $servicesDiscount = (float) ($serviceBookings->sum('discount_amount') ?? 0);

        return response()->json([
            'period' => [
                'from' => $validated['from'] ?? null,
                'to' => $validated['to'] ?? null,
            ],
            'revenue' => [
                'orders_total' => $ordersRevenue,
                'orders_subtotal' => $ordersSubtotal,
                'orders_shipping' => $ordersShipping,
                'services' => $servicesRevenue,
                'total' => $ordersRevenue + $servicesRevenue,
            ],
            'cogs' => [
                'orders' => $ordersCogs,
            ],
            'profit' => [
                'orders_gross_profit' => $ordersRevenue - $ordersCogs,
            ],
            'platform' => [
                'service_markup_gross' => $servicesMarkup,
                'service_discount' => $servicesDiscount,
                'service_markup_net' => $servicesMarkup - $servicesDiscount,
            ],
            'notes' => [
                'orders_profit' => 'Order gross profit = total_amount - total_cost (biaya real pengiriman belum dicatat).',
            ],
        ]);
    }
}
