<?php

use App\Models\JournalEntry;
use App\Models\PartnerProfile;
use App\Models\Payment;
use App\Models\Service;
use App\Models\ServiceBooking;
use App\Models\User;
use App\Services\BalanceService;
use App\Services\JournalService;

it('posts a balanced journal when a service booking payment is paid', function () {
    $patient = User::factory()->create(['role' => 'pasien']);
    $partner = User::factory()->create(['role' => 'mitra']);

    PartnerProfile::create([
        'user_id' => $partner->id,
        'profession' => 'perawat',
        'verification_status' => 'verified',
    ]);

    $service = Service::create([
        'service_code' => 'SVC-JOURNAL-PAYMENT',
        'name' => 'Homecare Journal Payment',
        'service_type' => 'procedure',
        'base_price' => 185000,
        'is_active' => true,
    ]);

    $booking = ServiceBooking::create([
        'booking_code' => 'SVC-JOURNAL-001',
        'service_id' => $service->id,
        'patient_user_id' => $patient->id,
        'assigned_partner_user_id' => $partner->id,
        'status' => 'confirmed',
        'total_amount' => 243500,
        'subtotal' => 203500,
        'markup_amount' => 18500,
        'transport_fee' => 25000,
        'meal_fee' => 15000,
    ]);

    $payment = Payment::create([
        'service_booking_id' => $booking->id,
        'patient_user_id' => $patient->id,
        'payment_code' => 'PAY-JOURNAL-SVC-001',
        'payment_method' => 'bank_transfer',
        'status' => 'paid',
        'amount' => 243500,
        'paid_at' => now(),
    ]);

    app(JournalService::class)->recordPaymentReceived($payment);

    $journal = JournalEntry::query()
        ->where('reference_type', 'payment')
        ->where('reference_id', $payment->id)
        ->with('lines')
        ->firstOrFail();

    expect($journal->status)->toBe('posted')
        ->and((float) $journal->lines->sum('debit'))->toBe(243500.0)
        ->and((float) $journal->lines->sum('credit'))->toBe(243500.0);

    $this->assertDatabaseHas('journal_lines', [
        'journal_entry_id' => $journal->id,
        'account_code' => '2101',
        'account_name' => 'Utang Mitra Layanan',
        'credit' => 225000,
    ]);

    $this->assertDatabaseHas('journal_lines', [
        'journal_entry_id' => $journal->id,
        'account_code' => '4103',
        'account_name' => 'Pendapatan Platform Layanan',
        'credit' => 18500,
    ]);
});

it('posts a balanced journal when partner payout is credited to wallet', function () {
    $partner = User::factory()->create(['role' => 'mitra']);
    $balance = app(BalanceService::class)->getOrCreateBalance($partner);

    $transaction = app(BalanceService::class)->credit($balance, 225000, [
        'reference_type' => 'service_booking',
        'reference_id' => 999,
        'idempotency_key' => 'journal:test:service_booking:payout',
        'description' => 'Pendapatan layanan SVC-JOURNAL-001',
    ]);

    $journal = JournalEntry::query()
        ->where('reference_type', 'balance_transaction')
        ->where('reference_id', $transaction->id)
        ->with('lines')
        ->firstOrFail();

    expect($journal->status)->toBe('posted')
        ->and((float) $journal->lines->sum('debit'))->toBe(225000.0)
        ->and((float) $journal->lines->sum('credit'))->toBe(225000.0);

    $this->assertDatabaseHas('journal_lines', [
        'journal_entry_id' => $journal->id,
        'account_code' => '2101',
        'account_name' => 'Utang Mitra Layanan',
        'debit' => 225000,
    ]);

    $this->assertDatabaseHas('journal_lines', [
        'journal_entry_id' => $journal->id,
        'account_code' => '2201',
        'account_name' => 'Saldo Mitra',
        'credit' => 225000,
    ]);
});
