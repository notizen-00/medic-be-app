<?php

namespace App\Services;

use App\Models\BalanceTransaction;
use App\Models\JournalEntry;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class JournalService
{
    public function recordPaymentReceived(Payment $payment): ?JournalEntry
    {
        if ($payment->status !== 'paid' || (float) $payment->amount <= 0) {
            return null;
        }

        $payment->loadMissing(['consultation', 'serviceBooking.service']);
        $amount = $this->money($payment->amount);
        [$cashCode, $cashName] = $this->cashAccountForMethod((string) $payment->payment_method);

        $lines = [[
            'account_code' => $cashCode,
            'account_name' => $cashName,
            'line_description' => $payment->payment_code,
            'debit' => $amount,
            'credit' => '0.00',
        ]];

        if ($payment->serviceBooking) {
            $booking = $payment->serviceBooking;
            $partnerPayout = min($amount, $this->money($booking->partnerPayoutAmount()));
            $platformRevenue = $this->money($amount - $partnerPayout);

            if ($partnerPayout > 0) {
                $lines[] = [
                    'account_code' => '2101',
                    'account_name' => 'Utang Mitra Layanan',
                    'line_description' => $booking->booking_code,
                    'debit' => '0.00',
                    'credit' => $partnerPayout,
                ];
            }

            if ($platformRevenue > 0) {
                $lines[] = [
                    'account_code' => '4103',
                    'account_name' => 'Pendapatan Platform Layanan',
                    'line_description' => $booking->booking_code,
                    'debit' => '0.00',
                    'credit' => $platformRevenue,
                ];
            }

            return $this->replacePostedEntry(
                'payment',
                $payment->id,
                $payment->paid_at ?? now(),
                'Pembayaran layanan '.$booking->booking_code,
                $lines
            );
        }

        if ($payment->consultation) {
            $lines[] = [
                'account_code' => '2102',
                'account_name' => 'Utang Mitra Konsultasi',
                'line_description' => $payment->consultation->consultation_code,
                'debit' => '0.00',
                'credit' => $amount,
            ];

            return $this->replacePostedEntry(
                'payment',
                $payment->id,
                $payment->paid_at ?? now(),
                'Pembayaran konsultasi '.$payment->consultation->consultation_code,
                $lines
            );
        }

        $lines[] = [
            'account_code' => '4101',
            'account_name' => 'Pendapatan Order',
            'line_description' => $payment->payment_code,
            'debit' => '0.00',
            'credit' => $amount,
        ];

        return $this->replacePostedEntry(
            'payment',
            $payment->id,
            $payment->paid_at ?? now(),
            'Pembayaran '.$payment->payment_code,
            $lines
        );
    }

    public function recordOrderRevenue(Order $order): ?JournalEntry
    {
        if ($order->status === 'cancelled') {
            $this->voidEntry('order', $order->id);

            return null;
        }

        if (! in_array($order->status, ['confirmed', 'processed', 'shipped', 'delivered'], true)) {
            return null;
        }

        $order->loadMissing('items');

        $total = $this->money($order->total_amount);
        if ($total <= 0) {
            return null;
        }

        $lines = [
            [
                'account_code' => '1104',
                'account_name' => 'Piutang Order',
                'line_description' => $order->order_code,
                'debit' => $total,
                'credit' => '0.00',
            ],
            [
                'account_code' => '4101',
                'account_name' => 'Pendapatan Order',
                'line_description' => $order->order_code,
                'debit' => '0.00',
                'credit' => $total,
            ],
        ];

        $totalCost = $this->money($order->items->sum(fn ($item) => (float) ($item->total_cost ?? 0)));
        if ($totalCost > 0) {
            $lines[] = [
                'account_code' => '5101',
                'account_name' => 'Harga Pokok Penjualan',
                'line_description' => $order->order_code,
                'debit' => $totalCost,
                'credit' => '0.00',
            ];
            $lines[] = [
                'account_code' => '1301',
                'account_name' => 'Persediaan Produk',
                'line_description' => $order->order_code,
                'debit' => '0.00',
                'credit' => $totalCost,
            ];
        }

        return $this->replacePostedEntry(
            'order',
            $order->id,
            $order->ordered_at ?? now(),
            'Pendapatan order '.$order->order_code,
            $lines
        );
    }

    public function recordPartnerPayout(BalanceTransaction $transaction): ?JournalEntry
    {
        if ($transaction->status !== 'completed' || (float) $transaction->amount <= 0) {
            return null;
        }

        $referenceType = (string) $transaction->reference_type;
        if (! in_array($referenceType, ['service_booking', 'consultation'], true)) {
            return null;
        }

        $amount = $this->money($transaction->amount);
        $liability = $referenceType === 'consultation'
            ? ['2102', 'Utang Mitra Konsultasi']
            : ['2101', 'Utang Mitra Layanan'];

        return $this->replacePostedEntry(
            'balance_transaction',
            $transaction->id,
            $transaction->created_at ?? now(),
            $transaction->description,
            [
                [
                    'account_code' => $liability[0],
                    'account_name' => $liability[1],
                    'line_description' => $transaction->reference_number,
                    'debit' => $amount,
                    'credit' => '0.00',
                ],
                [
                    'account_code' => '2201',
                    'account_name' => 'Saldo Mitra',
                    'line_description' => $transaction->reference_number,
                    'debit' => '0.00',
                    'credit' => $amount,
                ],
            ]
        );
    }

    private function replacePostedEntry(string $referenceType, int $referenceId, mixed $entryDate, ?string $description, array $lines): JournalEntry
    {
        return DB::transaction(function () use ($referenceType, $referenceId, $entryDate, $description, $lines) {
            $entry = JournalEntry::query()->updateOrCreate(
                [
                    'reference_type' => $referenceType,
                    'reference_id' => $referenceId,
                ],
                [
                    'entry_date' => $entryDate instanceof \DateTimeInterface ? $entryDate->format('Y-m-d') : (string) $entryDate,
                    'description' => $description,
                    'status' => 'posted',
                    'posted_at' => now(),
                ]
            );

            $entry->lines()->delete();
            $entry->lines()->createMany($this->normalizeLines($lines)->all());

            return $entry->load('lines');
        });
    }

    private function voidEntry(string $referenceType, int $referenceId): void
    {
        JournalEntry::query()
            ->where('reference_type', $referenceType)
            ->where('reference_id', $referenceId)
            ->where('status', '!=', 'void')
            ->update(['status' => 'void']);
    }

    private function normalizeLines(array $lines): Collection
    {
        return collect($lines)
            ->filter(fn (array $line) => (float) $line['debit'] > 0 || (float) $line['credit'] > 0)
            ->map(fn (array $line) => [
                'account_code' => $line['account_code'],
                'account_name' => $line['account_name'],
                'line_description' => $line['line_description'] ?? null,
                'debit' => number_format((float) $line['debit'], 2, '.', ''),
                'credit' => number_format((float) $line['credit'], 2, '.', ''),
            ])
            ->values();
    }

    private function money(float|string|null $amount): float
    {
        return round((float) ($amount ?? 0), 2);
    }

    /**
     * @return array{0:string,1:string}
     */
    private function cashAccountForMethod(string $method): array
    {
        return match ($method) {
            'wallet' => ['1102', 'Kas Wallet'],
            'cash' => ['1100', 'Kas Tunai'],
            'credit_card' => ['1103', 'Kas Kartu Kredit'],
            default => ['1101', 'Kas/Bank'],
        };
    }
}
