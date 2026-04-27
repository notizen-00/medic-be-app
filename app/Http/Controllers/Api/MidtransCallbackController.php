<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class MidtransCallbackController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'order_id' => ['required', 'string'],
            'status_code' => ['required', 'string'],
            'gross_amount' => ['required'],
            'signature_key' => ['required', 'string'],
            'transaction_status' => ['required', 'string'],
            'fraud_status' => ['nullable', 'string'],
            'payment_type' => ['nullable', 'string'],
            'settlement_time' => ['nullable', 'string'],
            'transaction_time' => ['nullable', 'string'],
        ]);

        $this->ensureValidSignature($payload);

        $payment = Payment::query()
            ->with('consultation')
            ->where('payment_code', $payload['order_id'])
            ->firstOrFail();

        $paymentStatus = $this->resolvePaymentStatus(
            $payload['transaction_status'],
            $payload['fraud_status'] ?? null
        );

        $paidAt = $paymentStatus === 'paid'
            ? $this->resolvePaidAt($payload['settlement_time'] ?? null, $payload['transaction_time'] ?? null)
            : null;

        DB::transaction(function () use ($payment, $payload, $paymentStatus, $paidAt): void {
            $payment->update([
                'payment_method' => $this->resolvePaymentMethod($payload['payment_type'] ?? null, $payment->payment_method),
                'status' => $paymentStatus,
                'paid_at' => $paidAt,
                'notes' => trim(sprintf(
                    'Midtrans %s via %s.',
                    $payload['transaction_status'],
                    $payload['payment_type'] ?? $payment->payment_method
                )),
            ]);

            if (! $payment->consultation) {
                return;
            }

            if ($paymentStatus === 'paid' && $payment->consultation->status === 'pending') {
                $payment->consultation->update([
                    'status' => 'confirmed',
                ]);
            }

            if (in_array($paymentStatus, ['failed', 'expired'], true) && $payment->consultation->status === 'pending') {
                $payment->consultation->update([
                    'status' => 'cancelled',
                ]);
            }
        });

        return response()->json([
            'message' => 'Callback Midtrans berhasil diproses.',
            'data' => [
                'payment_code' => $payment->payment_code,
                'status' => $paymentStatus,
            ],
        ]);
    }

    private function ensureValidSignature(array $payload): void
    {
        $expectedSignature = hash(
            'sha512',
            $payload['order_id'] . $payload['status_code'] . $payload['gross_amount'] . config('midtrans.server_key')
        );

        if (! hash_equals($expectedSignature, $payload['signature_key'])) {
            throw new AccessDeniedHttpException('Signature Midtrans tidak valid.');
        }
    }

    private function resolvePaymentStatus(string $transactionStatus, ?string $fraudStatus): string
    {
        return match ($transactionStatus) {
            'capture' => $fraudStatus === 'challenge' ? 'pending' : 'paid',
            'settlement' => 'paid',
            'pending' => 'pending',
            'deny', 'cancel' => 'failed',
            'expire' => 'expired',
            'refund', 'partial_refund', 'chargeback', 'partial_chargeback' => 'refunded',
            default => 'pending',
        };
    }

    private function resolvePaymentMethod(?string $paymentType, string $default): string
    {
        return match ($paymentType) {
            'credit_card' => 'credit_card',
            'bank_transfer', 'echannel', 'permata' => 'bank_transfer',
            'gopay', 'qris', 'shopeepay', 'akulaku' => 'wallet',
            'cstore' => 'cash',
            default => $default,
        };
    }

    private function resolvePaidAt(?string $settlementTime, ?string $transactionTime): ?Carbon
    {
        $timestamp = $settlementTime ?: $transactionTime;

        return $timestamp ? Carbon::parse($timestamp) : now();
    }
}
