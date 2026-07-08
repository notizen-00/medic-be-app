<?php

use App\Models\BalanceTransaction;
use App\Models\User;
use App\Models\UserBalance;
use Laravel\Sanctum\Sanctum;

it('shows mitra balance summary', function () {
    $mitra = User::factory()->create(['role' => 'mitra']);

    UserBalance::create([
        'user_id' => $mitra->id,
        'balance' => 250000,
        'reserved_balance' => 50000,
        'status' => 'active',
    ]);

    Sanctum::actingAs($mitra);

    $this->getJson('/api/mitra/balance')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.summary.current_balance', 250000)
        ->assertJsonPath('data.summary.available_balance', 200000)
        ->assertJsonPath('data.balance.balance', '250000.00');
});

it('shows mitra balance transaction history', function () {
    $mitra = User::factory()->create(['role' => 'mitra']);
    $balance = UserBalance::create([
        'user_id' => $mitra->id,
        'balance' => 150000,
        'reserved_balance' => 0,
        'status' => 'active',
    ]);

    BalanceTransaction::create([
        'user_id' => $mitra->id,
        'balance_id' => $balance->id,
        'transaction_uuid' => BalanceTransaction::generateTransactionUuid(),
        'type' => 'topup',
        'status' => 'completed',
        'amount' => 150000,
        'balance_before' => 0,
        'balance_after' => 150000,
        'reference_number' => BalanceTransaction::generateReferenceNumber('topup'),
        'description' => 'Pendapatan layanan test',
    ]);

    Sanctum::actingAs($mitra);

    $this->getJson('/api/mitra/balance/history?type=topup')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.data.0.amount', '150000.00')
        ->assertJsonPath('data.data.0.description', 'Pendapatan layanan test');
});
