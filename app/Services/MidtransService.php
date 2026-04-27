<?php

namespace App\Services;

use App\Models\Payment;
use Illuminate\Support\Facades\Log;
use Throwable;
use RuntimeException;

class MidtransService
{
    public function __construct()
    {
        $configClass = 'Midtrans\\Config';
        $snapClass = 'Midtrans\\Snap';

        if (! class_exists($configClass) || ! class_exists($snapClass)) {
            throw new RuntimeException('Package midtrans/midtrans-php belum tersedia di vendor. Jalankan composer install terlebih dahulu.');
        }

        if (! config('midtrans.server_key')) {
            throw new RuntimeException('MIDTRANS_SERVER_KEY belum diatur pada konfigurasi aplikasi.');
        }

        if (! config('midtrans.client_key')) {
            throw new RuntimeException('MIDTRANS_CLIENT_KEY belum diatur pada konfigurasi aplikasi.');
        }

        $configClass::$serverKey = (string) config('midtrans.server_key');
        $configClass::$clientKey = (string) config('midtrans.client_key');
        $configClass::$isProduction = (bool) config('midtrans.is_production');
        $configClass::$isSanitized = (bool) config('midtrans.is_sanitized');
        $configClass::$is3ds = (bool) config('midtrans.is_3ds');
    }

    public function getOrCreateSnapTransaction(Payment $payment): array
    {
        $payment->loadMissing(['patient', 'consultation']);

        Log::info('Memulai proses Snap Midtrans.', [
            'payment_id' => $payment->id,
            'payment_code' => $payment->payment_code,
            'consultation_id' => $payment->consultation_id,
            'patient_user_id' => $payment->patient_user_id,
            'status' => $payment->status,
            'amount' => (float) $payment->amount,
            'has_existing_snap_token' => ! empty($payment->snap_token),
        ]);

        if ($payment->status === 'pending' && $payment->snap_token) {
            Log::info('Menggunakan ulang Snap token Midtrans yang masih aktif.', [
                'payment_id' => $payment->id,
                'payment_code' => $payment->payment_code,
                'snap_token_created_at' => $payment->snap_token_created_at,
            ]);

            return [
                'token' => $payment->snap_token,
                'redirect_url' => $payment->snap_redirect_url,
                'order_id' => $payment->payment_code,
                'gross_amount' => (int) round((float) $payment->amount),
                'is_reused' => true,
            ];
        }

        $params = [
            'transaction_details' => [
                'order_id' => $payment->payment_code,
                'gross_amount' => (int) round((float) $payment->amount),
            ],
            'item_details' => [[
                'id' => $payment->payment_code,
                'price' => (int) round((float) $payment->amount),
                'quantity' => 1,
                'name' => $payment->consultation?->consultation_code
                    ? 'Pembayaran Konsultasi ' . $payment->consultation->consultation_code
                    : 'Pembayaran Konsultasi',
            ]],
            'customer_details' => [
                'first_name' => $payment->patient?->name ?? 'Pelanggan',
                'email' => $payment->patient?->email,
                'phone' => $payment->patient?->phone,
            ],
        ];

        $callbacks = array_filter([
            'finish' => config('midtrans.finish_url'),
            'unfinish' => config('midtrans.unfinish_url'),
            'error' => config('midtrans.error_url'),
        ]);

        if ($callbacks !== []) {
            $params['callbacks'] = $callbacks;
        }

        Log::info('Mengirim request Snap transaction ke Midtrans.', [
            'payment_id' => $payment->id,
            'payment_code' => $payment->payment_code,
            'transaction_details' => $params['transaction_details'],
            'item_details' => $params['item_details'],
            'customer_details' => [
                'first_name' => $params['customer_details']['first_name'] ?? null,
                'email' => $params['customer_details']['email'] ?? null,
                'phone' => $params['customer_details']['phone'] ?? null,
            ],
            'has_callbacks' => array_key_exists('callbacks', $params),
        ]);

        $snapClass = 'Midtrans\\Snap';
        try {
            $transaction = $snapClass::createTransaction($params);
        } catch (Throwable $exception) {
            Log::error('Midtrans gagal membuat Snap transaction.', [
                'payment_id' => $payment->id,
                'payment_code' => $payment->payment_code,
                'consultation_id' => $payment->consultation_id,
                'error' => $exception->getMessage(),
            ]);

            throw new RuntimeException(
                'Gagal membuat transaksi Snap Midtrans: ' . $exception->getMessage(),
                previous: $exception
            );
        }

        $snapToken = data_get($transaction, 'token');
        $snapRedirectUrl = data_get($transaction, 'redirect_url');

        Log::info('Respons Snap Midtrans diterima.', [
            'payment_id' => $payment->id,
            'payment_code' => $payment->payment_code,
            'response_type' => get_debug_type($transaction),
            'has_token' => ! empty($snapToken),
            'has_redirect_url' => ! empty($snapRedirectUrl),
        ]);

        $payment->update([
            'snap_token' => $snapToken,
            'snap_redirect_url' => $snapRedirectUrl,
            'snap_token_created_at' => now(),
        ]);

        $payment->refresh();

        Log::info('Snap transaction Midtrans berhasil dibuat.', [
            'payment_id' => $payment->id,
            'payment_code' => $payment->payment_code,
            'has_snap_token' => ! empty($payment->snap_token),
            'has_redirect_url' => ! empty($payment->snap_redirect_url),
        ]);

        return [
            'token' => $payment->snap_token,
            'redirect_url' => $payment->snap_redirect_url,
            'order_id' => $payment->payment_code,
            'gross_amount' => (int) round((float) $payment->amount),
            'is_reused' => false,
        ];
    }
}
