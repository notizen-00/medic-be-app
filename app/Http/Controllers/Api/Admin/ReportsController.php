<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\ServiceBooking;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
            'status' => ['nullable', 'string', 'max:50'],
            'patient_user_id' => ['nullable', 'integer', 'min:1'],
            'pharmacy_id' => ['nullable', 'integer', 'min:1'],
        ]);

        $query = Order::query();

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

        $totalOrders = (clone $query)->count();
        $totalAmount = (float) ((clone $query)->sum('total_amount') ?? 0);
        $paidAmount = (float) ((clone $query)
            ->whereIn('status', ['confirmed', 'processed', 'shipped', 'delivered'])
            ->sum('total_amount') ?? 0);
        $pendingAmount = (float) ((clone $query)->where('status', 'pending')->sum('total_amount') ?? 0);
        $cancelledOrders = (clone $query)->where('status', 'cancelled')->count();
        $completedOrders = (clone $query)->whereIn('status', ['delivered', 'completed'])->count();

        return response()->json([
            'message' => 'OK',
            'data' => [
                'total_orders' => $totalOrders,
                'orders_count' => $totalOrders,
                'total_count' => $totalOrders,
                'count' => $totalOrders,
                'total_amount' => $totalAmount,
                'paid_amount' => $paidAmount,
                'pending_amount' => $pendingAmount,
                'cancelled_orders' => $cancelledOrders,
                'completed_orders' => $completedOrders,
            ],
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

        $customers = User::query()->where('role', 'pasien');
        $orders = Order::query();

        if (!empty($validated['from'])) {
            $customers->whereDate('created_at', '>=', $validated['from']);
        }
        if (!empty($validated['to'])) {
            $customers->whereDate('created_at', '<=', $validated['to']);
        }

        if (!empty($validated['from'])) {
            $orders->whereDate('ordered_at', '>=', $validated['from']);
        }
        if (!empty($validated['to'])) {
            $orders->whereDate('ordered_at', '<=', $validated['to']);
        }

        $activeCustomerIds = (clone $orders)
            ->whereNotNull('patient_user_id')
            ->distinct()
            ->pluck('patient_user_id');

        $totalCustomers = User::query()->where('role', 'pasien')->count();
        $newCustomers = $customers->count();
        $activeCustomers = $activeCustomerIds->count();
        $inactiveCustomers = max(0, $totalCustomers - $activeCustomers);

        return response()->json([
            'message' => 'OK',
            'data' => [
                'total_customers' => $totalCustomers,
                'new_customers' => $newCustomers,
                'active_customers' => $activeCustomers,
                'inactive_customers' => $inactiveCustomers,
                'customers_count' => $newCustomers,
                'count' => $newCustomers,
            ],
        ]);
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

        $totalRevenue = $ordersRevenue + $servicesRevenue;
        $grossProfit = $totalRevenue - $ordersCogs;
        $operationalCost = 0.0;
        $platformProfit = ($ordersRevenue - $ordersCogs) + ($servicesMarkup - $servicesDiscount);
        $netProfit = $grossProfit - $operationalCost;

        return response()->json([
            'message' => 'OK',
            'data' => [
                'revenue' => $totalRevenue,
                'total_revenue' => $totalRevenue,
                'income' => $totalRevenue,
                'gross_revenue' => $totalRevenue,
                'total_income' => $totalRevenue,
                'cogs' => $ordersCogs,
                'cost_of_goods_sold' => $ordersCogs,
                'cost' => $ordersCogs,
                'total_cost' => $ordersCogs,
                'gross_profit' => $grossProfit,
                'operational_cost' => $operationalCost,
                'operating_expense' => $operationalCost,
                'expense' => $operationalCost,
                'total_expense' => $operationalCost,
                'app_profit' => $platformProfit,
                'applicator_profit' => $platformProfit,
                'platform_profit' => $platformProfit,
                'laba_aplikator' => $platformProfit,
                'net_profit' => $netProfit,
                'profit' => $netProfit,
                'orders_revenue' => $ordersRevenue,
                'services_revenue' => $servicesRevenue,
                'service_markup_net' => $servicesMarkup - $servicesDiscount,
            ],
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
