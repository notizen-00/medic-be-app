<?php

use App\Models\BalanceTransaction;
use App\Models\JournalEntry;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Models\UserBalance;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
});

it('returns admin report summaries with contract fields when data is empty', function () {
    $this->getJson('/api/admin/reports/orders')
        ->assertOk()
        ->assertJsonPath('message', 'OK')
        ->assertJsonStructure([
            'data' => [
                'total_orders',
                'orders_count',
                'total_count',
                'count',
                'total_amount',
                'paid_amount',
                'pending_amount',
                'cancelled_orders',
                'completed_orders',
            ],
        ])
        ->assertJsonPath('data.total_orders', 0)
        ->assertJsonPath('data.total_amount', 0);

    $this->getJson('/api/admin/reports/customers')
        ->assertOk()
        ->assertJsonPath('message', 'OK')
        ->assertJsonStructure([
            'data' => [
                'total_customers',
                'new_customers',
                'active_customers',
                'inactive_customers',
                'customers_count',
                'count',
            ],
        ]);

    $this->getJson('/api/admin/reports/profit-loss')
        ->assertOk()
        ->assertJsonPath('message', 'OK')
        ->assertJsonStructure([
            'data' => [
                'revenue',
                'total_revenue',
                'cogs',
                'gross_profit',
                'operational_cost',
                'app_profit',
                'platform_profit',
                'net_profit',
            ],
        ])
        ->assertJsonPath('data.revenue', 0)
        ->assertJsonPath('data.net_profit', 0);
});

it('returns admin balance transactions and journals in report friendly shape', function () {
    $patient = User::factory()->create(['role' => 'pasien']);
    $balance = UserBalance::create([
        'user_id' => $patient->id,
        'balance' => 150000,
        'reserved_balance' => 0,
        'status' => 'active',
    ]);

    BalanceTransaction::create([
        'user_id' => $patient->id,
        'balance_id' => $balance->id,
        'transaction_uuid' => 'trx-uuid-1',
        'idempotency_key' => 'test-admin-report-1',
        'type' => 'topup',
        'status' => 'completed',
        'amount' => 150000,
        'balance_before' => 0,
        'balance_after' => 150000,
        'reference_type' => 'manual',
        'reference_id' => 1,
        'description' => 'Topup test',
        'reference_number' => 'TOP-TEST-001',
    ]);

    $journal = JournalEntry::create([
        'entry_date' => '2026-07-13',
        'description' => 'Jurnal test',
        'reference_type' => 'manual',
        'reference_id' => 1,
        'status' => 'posted',
    ]);
    $journal->lines()->createMany([
        ['account_code' => '1000', 'account_name' => 'Kas', 'debit' => 150000, 'credit' => 0],
        ['account_code' => '4000', 'account_name' => 'Pendapatan', 'debit' => 0, 'credit' => 150000],
    ]);

    $this->getJson('/api/admin/balance?per_page=100')
        ->assertOk()
        ->assertJsonPath('message', 'OK')
        ->assertJsonPath('data.data.0.balance', 150000)
        ->assertJsonPath('data.data.0.amount', 150000)
        ->assertJsonPath('data.data.0.saldo', 150000);

    $this->getJson('/api/admin/balance/transactions?per_page=100')
        ->assertOk()
        ->assertJsonPath('message', 'OK')
        ->assertJsonPath('data.data.0.transaction_code', 'TOP-TEST-001')
        ->assertJsonPath('data.data.0.amount', 150000)
        ->assertJsonPath('data.data.0.transaction_date', fn ($value) => is_string($value));

    $this->getJson('/api/admin/journals?per_page=100')
        ->assertOk()
        ->assertJsonPath('message', 'OK')
        ->assertJsonPath('data.data.0.totals.debit', 150000)
        ->assertJsonPath('data.data.0.totals.credit', 150000)
        ->assertJsonPath('data.data.0.total_debit', 150000)
        ->assertJsonPath('data.data.0.total_credit', 150000)
        ->assertJsonPath('data.data.0.is_balanced', true);
});

it('returns a unified admin transactions paginator', function () {
    $patient = User::factory()->create(['role' => 'pasien']);
    $pharmacyOwner = User::factory()->create(['role' => 'mitra']);

    Payment::create([
        'patient_user_id' => $patient->id,
        'payment_code' => 'PAY-REPORT-001',
        'status' => 'paid',
        'amount' => 99000,
    ]);

    Order::create([
        'order_code' => 'ORD-REPORT-001',
        'patient_user_id' => $patient->id,
        'pharmacy_user_id' => $pharmacyOwner->id,
        'status' => 'delivered',
        'subtotal' => 50000,
        'shipping_cost' => 10000,
        'total_amount' => 60000,
        'ordered_at' => now(),
    ]);

    $this->getJson('/api/admin/transactions?per_page=100')
        ->assertOk()
        ->assertJsonPath('message', 'OK')
        ->assertJsonStructure([
            'data' => [
                'data' => [
                    '*' => [
                        'transaction_code',
                        'reference',
                        'reference_type',
                        'type',
                        'amount',
                        'status',
                        'created_at',
                    ],
                ],
                'current_page',
                'last_page',
                'per_page',
                'total',
            ],
        ]);
});
