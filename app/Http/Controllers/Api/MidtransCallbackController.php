<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Consultation;
use App\Models\ServiceBooking;
use App\Services\AppNotificationService;
use App\Services\JournalService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class MidtransCallbackController extends Controller
{
    public function __construct(
        private readonly AppNotificationService $notifications,
        private readonly JournalService $journals
    ) {
    }

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
            ->where('payment_code', $payload['order_id'])
            ->firstOrFail();

        if (abs((float) $payload['gross_amount'] - (float) $payment->amount) > 0.01) {
            throw new AccessDeniedHttpException('Nominal callback Midtrans tidak sesuai dengan tagihan.');
        }

        $paymentStatus = $this->resolvePaymentStatus(
            $payload['transaction_status'],
            $payload['fraud_status'] ?? null
        );

        $paidAt = $paymentStatus === 'paid'
            ? $this->resolvePaidAt($payload['settlement_time'] ?? null, $payload['transaction_time'] ?? null)
            : null;

        $effectiveStatus = DB::transaction(function () use ($payment, $payload, $paymentStatus, $paidAt): string {
            $consultation = $payment->consultation_id
                ? Consultation::query()->lockForUpdate()->findOrFail($payment->consultation_id)
                : null;
            $booking = $payment->service_booking_id
                ? ServiceBooking::query()->lockForUpdate()->findOrFail($payment->service_booking_id)
                : null;
            $lockedPayment = Payment::query()->lockForUpdate()->findOrFail($payment->id);

            if ($lockedPayment->status === 'paid' && in_array($paymentStatus, ['pending', 'failed', 'expired'], true)) {
                return 'paid';
            }

            if ($paymentStatus === 'refunded' && ($booking?->partner_balance_transaction_id || $consultation?->partner_balance_transaction_id)) {
                throw new ConflictHttpException('Refund memerlukan rekonsiliasi karena dana sudah dikreditkan ke saldo mitra.');
            }

            $lockedPayment->update([
                'payment_method' => $this->resolvePaymentMethod($payload['payment_type'] ?? null, $payment->payment_method),
                'status' => $paymentStatus,
                'paid_at' => $paymentStatus === 'paid' ? ($lockedPayment->paid_at ?? $paidAt) : $lockedPayment->paid_at,
                'notes' => trim(sprintf(
                    'Midtrans %s via %s.',
                    $payload['transaction_status'],
                    $payload['payment_type'] ?? $payment->payment_method
                )),
            ]);

            if (! $consultation) {
                if ($booking) {
                    $lockedPayment->setRelation('serviceBooking', $booking);
                    $this->handleServiceBookingPayment($lockedPayment, $paymentStatus);
                }

                $this->journals->recordPaymentReceived($lockedPayment->fresh(['serviceBooking.service']));

                return $paymentStatus;
            }

            if ($paymentStatus === 'paid' && in_array($consultation->status, ['pending', 'cancelled'], true)) {
                $consultation->update([
                    'status' => 'confirmed',
                ]);
            }

            if (in_array($paymentStatus, ['failed', 'expired'], true) && $consultation->status === 'pending') {
                $consultation->update([
                    'status' => 'cancelled',
                ]);
            }

            if ($paymentStatus === 'refunded' && $consultation->status !== 'completed') {
                $consultation->update(['status' => 'cancelled', 'ended_at' => $consultation->ended_at ?? now()]);
            }

            $this->journals->recordPaymentReceived($lockedPayment->fresh(['consultation']));

            return $paymentStatus;
        }, 5);

        return response()->json([
            'message' => 'Callback Midtrans berhasil diproses.',
            'data' => [
                'payment_code' => $payment->payment_code,
                'status' => $effectiveStatus,
            ],
        ]);
    }

    private function handleServiceBookingPayment(Payment $payment, string $paymentStatus): void
    {
        $booking = $payment->serviceBooking;

        if (! $booking) {
            return;
        }

        if ($paymentStatus === 'paid' && in_array($booking->status, ['pending', 'confirmed', 'scheduled', 'cancelled'], true)) {
            if ($booking->status === 'cancelled') {
                $booking->update([
                    'status' => $booking->scheduled_at ? 'scheduled' : 'confirmed',
                    'completed_at' => null,
                ]);
            }

            if ($booking->status === 'pending' && $booking->scheduled_at) {
                $booking->update([
                    'status' => 'scheduled',
                ]);
            }

            if ($booking->assigned_partner_user_id) {
                $this->notifications->send($booking->assigned_partner_user_id, [
                    'type' => 'service_booking.paid',
                    'title' => 'Pembayaran layanan diterima',
                    'body' => 'Pembayaran untuk pesanan '.$booking->booking_code.' sudah diterima. Anda bisa mulai berangkat sesuai jadwal.',
                    'action_url' => '/mitra/service-bookings/'.$booking->id,
                    'reference_type' => 'service_booking',
                    'reference_id' => $booking->id,
                    'data' => [
                        'service_booking_id' => $booking->id,
                        'booking_code' => $booking->booking_code,
                        'status' => $booking->status,
                        'payment_status' => 'paid',
                    ],
                ]);
            }
        }

        if (in_array($paymentStatus, ['failed', 'expired'], true) && $booking->status === 'pending') {
            $booking->update([
                'status' => 'cancelled',
            ]);
        }

        if ($paymentStatus === 'refunded' && $booking->status !== 'completed') {
            $booking->update(['status' => 'cancelled', 'completed_at' => $booking->completed_at ?? now()]);
        }
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
