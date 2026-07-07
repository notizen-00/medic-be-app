<?php

namespace Database\Seeders;

use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class JournalSeeder extends Seeder
{
    public function run(): void
    {
        $adminUserId = User::query()
            ->where('email', 'admin.jember@example.com')
            ->value('id');

        $payments = Payment::query()
            ->where('status', 'paid')
            ->where('payment_code', 'like', 'PAY-ORD-%')
            ->get();

        foreach ($payments as $payment) {
            $orderCode = Str::startsWith($payment->payment_code, 'PAY-')
                ? Str::after($payment->payment_code, 'PAY-')
                : null;

            $order = $orderCode
                ? Order::query()->where('order_code', $orderCode)->first()
                : null;

            $entryDate = $payment->paid_at
                ? Carbon::parse($payment->paid_at)->toDateString()
                : now()->toDateString();

            $amount = (float) ($order?->total_amount ?? $payment->amount ?? 0);
            if ($amount <= 0) {
                continue;
            }

            [$cashCode, $cashName] = $this->cashAccountForMethod((string) $payment->payment_method);

            $referenceType = $order ? 'order' : 'payment';
            $referenceId = $order?->id ?? $payment->id;

            $entry = JournalEntry::query()->updateOrCreate(
                [
                    'reference_type' => $referenceType,
                    'reference_id' => $referenceId,
                ],
                [
                    'entry_date' => $entryDate,
                    'description' => $payment->notes ?: ($order ? "Pembayaran {$order->order_code}" : "Pembayaran {$payment->payment_code}"),
                    'status' => 'posted',
                    'posted_at' => $payment->paid_at ?? now(),
                    'created_by_user_id' => $adminUserId,
                ]
            );

            JournalLine::query()->where('journal_entry_id', $entry->id)->delete();

            JournalLine::query()->create([
                'journal_entry_id' => $entry->id,
                'account_code' => $cashCode,
                'account_name' => $cashName,
                'line_description' => $payment->payment_code,
                'debit' => number_format($amount, 2, '.', ''),
                'credit' => number_format(0, 2, '.', ''),
            ]);

            JournalLine::query()->create([
                'journal_entry_id' => $entry->id,
                'account_code' => '4101',
                'account_name' => 'Pendapatan Order',
                'line_description' => $order?->order_code ?? $payment->payment_code,
                'debit' => number_format(0, 2, '.', ''),
                'credit' => number_format($amount, 2, '.', ''),
            ]);
        }
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

