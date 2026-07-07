<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Events\ServiceBookingMatched;
use App\Models\Payment;
use App\Services\AppNotificationService;
use App\Services\ServicePartnerSelectionService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Throwable;

class MidtransCallbackController extends Controller
{
    public function __construct(
        private readonly AppNotificationService $notifications,
        private readonly ServicePartnerSelectionService $servicePartnerSelectionService
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
            ->with(['consultation', 'serviceBooking.assignedPartner'])
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
                $this->handleServiceBookingPayment($payment, $paymentStatus);

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

    private function handleServiceBookingPayment(Payment $payment, string $paymentStatus): void
    {
        $booking = $payment->serviceBooking;

        if (! $booking) {
            return;
        }

        if ($paymentStatus === 'paid' && $booking->status === 'pending') {
            $booking->update([
                'status' => $booking->scheduled_at ? 'scheduled' : 'pending',
            ]);

            $matchmaking = null;

            if (! $booking->assigned_partner_user_id) {
                try {
                    $matchmaking = $this->servicePartnerSelectionService->matchBookingAfterPayment($booking);
                    $booking->refresh();
                    $booking->loadMissing(['service', 'patient', 'assignedPartner.partnerProfile', 'address']);
                } catch (Throwable $exception) {
                    Log::warning('Service booking paid but matchmaking failed.', [
                        'service_booking_id' => $booking->id,
                        'payment_id' => $payment->id,
                        'error' => $exception->getMessage(),
                    ]);
                }
            }

            if ($matchmaking && empty($matchmaking['already_assigned'])) {
                ServiceBookingMatched::dispatch($booking, $matchmaking);
            }

            if ($booking->assigned_partner_user_id) {
                $this->notifications->send($booking->assigned_partner_user_id, [
                    'type' => $matchmaking ? 'service_booking.matched' : 'service_booking.paid',
                    'title' => $matchmaking ? 'Pesanan layanan baru' : 'Pembayaran layanan diterima',
                    'body' => $matchmaking
                        ? 'Ada pesanan layanan baru yang cocok untuk Anda.'
                        : 'Pembayaran untuk pesanan '.$booking->booking_code.' sudah diterima.',
                    'action_url' => '/mitra/service-bookings/'.$booking->id,
                    'reference_type' => 'service_booking',
                    'reference_id' => $booking->id,
                    'data' => [
                        'service_booking_id' => $booking->id,
                        'booking_code' => $booking->booking_code,
                        'status' => $booking->status,
                        'matchmaking' => $matchmaking,
                    ],
                ]);
            }
        }

        if (in_array($paymentStatus, ['failed', 'expired'], true) && $booking->status === 'pending') {
            $booking->update([
                'status' => 'cancelled',
            ]);
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
