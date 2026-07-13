<?php

namespace App\Http\Controllers\Api\Patient;

use App\Http\Controllers\Controller;
use App\Models\Consultation;
use App\Models\ConsultationMessage;
use App\Models\Payment;
use App\Models\PartnerProfile;
use App\Models\User;
use App\Services\MidtransService;
use App\Services\AppNotificationService;
use App\Services\ConsultationPayoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;
use Illuminate\Validation\ValidationException;

class ConsultationController extends Controller
{
    public function __construct(
        private readonly MidtransService $midtransService,
        private readonly AppNotificationService $notifications,
        private readonly ConsultationPayoutService $consultationPayouts
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $this->ensureAuthenticatedPatient($request);

        $validated = $request->validate([
            'status' => ['nullable', 'in:pending,confirmed,ongoing,completed,cancelled'],
            'partner_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $perPage = $this->resolvePerPage($request);

        $consultations = Consultation::query()
            ->with(['patient', 'partner.partnerProfile', 'messages.sender', 'prescription.items', 'payment'])
            ->where('patient_user_id', $user->id)
            ->when(
                $validated['partner_user_id'] ?? null,
                fn($query, $partnerId) => $query->where('partner_user_id', $partnerId)
            )
            ->when(
                $validated['status'] ?? null,
                fn($query, $status) => $query->where('status', $status)
            )
            ->latest()
            ->paginate($perPage)
            ->withQueryString();

        return response()->json([
            'message' => 'Daftar konsultasi pasien berhasil diambil.',
            'data' => $consultations,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $this->ensureAuthenticatedPatient($request);

        $validated = $request->validate([
            'partner_user_id' => ['required', 'integer', 'exists:users,id'],
            'service_type' => ['required', 'in:chat,voice_call,video_call,visit'],
            'scheduled_at' => ['nullable', 'date'],
            'complaint' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ]);

        $partnerProfile = PartnerProfile::where('user_id', $validated['partner_user_id'])->first();

        if (! $partnerProfile || $partnerProfile->profession !== 'dokter') {
            throw ValidationException::withMessages([
                'partner_user_id' => ['Konsultasi hanya dapat dibuat dengan mitra dokter.'],
            ]);
        }

        $consultation = DB::transaction(function () use ($validated, $partnerProfile, $user) {
            $consultation = Consultation::create([
                'consultation_code' => 'KONS-' . now()->format('YmdHis') . '-' . str_pad((string) random_int(1, 999), 3, '0', STR_PAD_LEFT),
                'patient_user_id' => $user->id,
                'partner_user_id' => $validated['partner_user_id'],
                'service_type' => $validated['service_type'],
                'status' => 'pending',
                'scheduled_at' => $validated['scheduled_at'] ?? null,
                'complaint' => $validated['complaint'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'consultation_fee' => $partnerProfile->consultation_fee ?? 0,
            ]);

            Payment::create([
                'consultation_id' => $consultation->id,
                'patient_user_id' => $user->id,
                'payment_code' => 'PAY-KONS-' . now()->format('YmdHis') . '-' . str_pad((string) random_int(1, 999), 3, '0', STR_PAD_LEFT),
                'status' => 'pending',
                'amount' => $consultation->consultation_fee,
                'notes' => 'Pembayaran konsultasi menunggu pelunasan sebelum sesi chat dibuka.',
            ]);

            return $consultation;
        });

        $consultation->load(['patient', 'partner.partnerProfile', 'payment']);

        $this->notifications->send($consultation->partner_user_id, [
            'type' => 'consultation.created',
            'title' => 'Konsultasi baru',
            'body' => $user->name.' membuat konsultasi baru.',
            'action_url' => '/mitra/consultations/'.$consultation->id,
            'reference_type' => 'consultation',
            'reference_id' => $consultation->id,
            'data' => [
                'consultation_id' => $consultation->id,
                'consultation_code' => $consultation->consultation_code,
                'patient_user_id' => $consultation->patient_user_id,
                'partner_user_id' => $consultation->partner_user_id,
                'status' => $consultation->status,
            ],
        ]);

        return response()->json([
            'message' => 'Konsultasi berhasil dibuat. Silakan selesaikan pembayaran sebelum membuka sesi chat.',
            'data' => $consultation,
        ], 201);
    }

    public function show(Request $request, Consultation $consultation): JsonResponse
    {
        $this->authorizePatientConsultation($request, $consultation);
        $this->ensureConsultationPaymentCompleted($consultation);

        $consultation->load(['patient', 'partner.partnerProfile', 'messages.sender', 'prescription.items', 'payment']);

        return response()->json([
            'message' => 'Detail konsultasi berhasil diambil.',
            'data' => $consultation,
        ]);
    }

    public function pay(Request $request, Consultation $consultation): JsonResponse
    {
        $this->authorizePatientConsultation($request, $consultation);

        $validated = $request->validate([
            'notes' => ['nullable', 'string'],
        ]);

        $payment = $consultation->payment;

        if (! $payment) {
            throw ValidationException::withMessages([
                'consultation' => ['Tagihan konsultasi tidak ditemukan.'],
            ]);
        }

        if ($payment->status === 'paid') {
            $consultation->load(['patient', 'partner.partnerProfile', 'payment']);

            return response()->json([
                'message' => 'Pembayaran konsultasi sudah lunas.',
                'data' => $consultation,
            ]);
        }

        $payment->update([
            'notes' => $validated['notes'] ?? $payment->notes,
        ]);

        $consultation->load(['patient', 'partner.partnerProfile', 'payment']);
        $payment = $payment->fresh(['patient', 'consultation']);

        try {
            $snap = $this->midtransService->getOrCreateSnapTransaction($payment);
        } catch (Throwable $exception) {
            Log::error('Gagal membuat Snap token Midtrans untuk consultation payment.', [
                'consultation_id' => $consultation->id,
                'payment_id' => $payment->id,
                'payment_code' => $payment->payment_code,
                'error' => $exception->getMessage(),
            ]);

            throw ValidationException::withMessages([
                'payment' => [$exception->getMessage()],
            ]);
        }

        return response()->json([
            'message' => $snap['is_reused']
                ? 'Snap token Midtrans lama masih aktif dan dipakai ulang untuk melanjutkan pembayaran.'
                : 'Transaksi Midtrans berhasil dibuat. Lanjutkan pembayaran untuk membuka sesi konsultasi.',
            'data' => [
                'consultation' => $consultation,
                'payment' => $payment->fresh(),
                'midtrans' => $snap,
            ],
        ]);
    }

    public function updateStatus(Request $request, Consultation $consultation): JsonResponse
    {
        $this->authorizePatientConsultation($request, $consultation);

        $validated = $request->validate([
            'status' => ['required', 'in:pending,confirmed,ongoing,completed,cancelled'],
            'diagnosis' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ]);

        $payload = [
            'status' => $validated['status'],
            'diagnosis' => $validated['diagnosis'] ?? $consultation->diagnosis,
            'notes' => $validated['notes'] ?? $consultation->notes,
        ];

        if (in_array($validated['status'], ['confirmed', 'ongoing', 'completed'], true)) {
            $this->ensureConsultationPaymentCompleted($consultation);
        }

        if ($validated['status'] === 'ongoing' && $consultation->started_at === null) {
            $payload['started_at'] = now();
        }

        if (in_array($validated['status'], ['completed', 'cancelled'], true) && $consultation->ended_at === null) {
            $payload['ended_at'] = now();
        }

        $consultation = DB::transaction(function () use ($consultation, $payload, $validated): Consultation {
            $lockedConsultation = Consultation::query()
                ->lockForUpdate()
                ->findOrFail($consultation->id);
            $payment = $lockedConsultation->payment()->lockForUpdate()->first();
            $lockedConsultation->load('partner');

            if (in_array($lockedConsultation->status, ['completed', 'cancelled'], true) && $validated['status'] !== $lockedConsultation->status) {
                throw ValidationException::withMessages(['status' => ['Status konsultasi final tidak dapat diubah kembali.']]);
            }

            if ($validated['status'] === 'cancelled' && ($payment?->status === 'paid' || $lockedConsultation->partner_balance_transaction_id !== null)) {
                throw ValidationException::withMessages(['status' => ['Konsultasi yang sudah dibayar atau payout harus melalui proses refund.']]);
            }

            if ($validated['status'] === 'completed' && $payment?->status !== 'paid') {
                throw ValidationException::withMessages(['payment' => ['Konsultasi hanya dapat diselesaikan setelah pembayaran lunas.']]);
            }

            $lockedConsultation->update($payload);

            if ($validated['status'] === 'completed' && $lockedConsultation->partner) {
                $this->consultationPayouts->creditPartnerIfNeeded($lockedConsultation, $lockedConsultation->partner, [
                    'confirmed_by_patient' => true,
                ]);
            }

            return $lockedConsultation;
        });

        $consultation->load(['patient', 'partner.partnerProfile', 'partnerBalanceTransaction']);

        if ($consultation->partner_user_id) {
            $this->notifications->send($consultation->partner_user_id, [
                'type' => 'consultation.status_updated',
                'title' => 'Status konsultasi diperbarui',
                'body' => 'Pasien memperbarui status konsultasi menjadi '.$consultation->status.'.',
                'action_url' => '/mitra/consultations/'.$consultation->id,
                'reference_type' => 'consultation',
                'reference_id' => $consultation->id,
                'data' => [
                    'consultation_id' => $consultation->id,
                    'status' => $consultation->status,
                ],
            ]);
        }

        return response()->json([
            'message' => 'Status konsultasi berhasil diperbarui.',
            'data' => $consultation,
        ]);
    }

    public function addMessage(Request $request, Consultation $consultation): JsonResponse
    {
        $user = $this->authorizePatientConsultation($request, $consultation);

        $validated = $request->validate([
            'message_type' => ['required', 'in:text,image,file,system'],
            'message' => ['nullable', 'string'],
            'attachment_path' => ['nullable', 'string', 'max:255'],
        ]);

        $this->ensureConsultationPaymentCompleted($consultation);

        $message = ConsultationMessage::create($validated + [
            'consultation_id' => $consultation->id,
            'sender_user_id' => $user->id,
        ]);

        $message->load('sender');

        $this->notifications->send($consultation->partner_user_id, [
            'type' => 'consultation.message_created',
            'title' => 'Pesan konsultasi baru',
            'body' => $user->name.': '.Str::limit($message->message ?? 'Mengirim lampiran', 120),
            'action_url' => '/mitra/consultations/'.$consultation->id,
            'reference_type' => 'consultation',
            'reference_id' => $consultation->id,
            'data' => [
                'consultation_id' => $consultation->id,
                'message_id' => $message->id,
                'sender_user_id' => $user->id,
            ],
        ]);

        return response()->json([
            'message' => 'Pesan konsultasi berhasil dikirim.',
            'data' => $message,
        ], 201);
    }

    private function authorizePatientConsultation(Request $request, Consultation $consultation): User
    {
        $user = $this->ensureAuthenticatedPatient($request);

        if ($consultation->patient_user_id !== $user->id) {
            throw ValidationException::withMessages([
                'consultation' => ['Konsultasi ini tidak berada dalam akses pasien yang sedang login.'],
            ]);
        }

        return $user;
    }

    private function ensureAuthenticatedPatient(Request $request): User
    {
        /** @var User|null $user */
        $user = $request->user();

        if (! $user) {
            throw ValidationException::withMessages([
                'user' => ['User login tidak ditemukan.'],
            ]);
        }

        if ($user->role !== 'pasien') {
            throw ValidationException::withMessages([
                'user' => ['Endpoint ini hanya dapat diakses oleh pasien.'],
            ]);
        }

        return $user;
    }

    private function ensureConsultationPaymentCompleted(Consultation $consultation): void
    {
        $consultation->loadMissing('payment');

        if (! $consultation->payment || $consultation->payment->status !== 'paid') {
            throw ValidationException::withMessages([
                'payment' => ['Sesi konsultasi belum dapat dibuka. Silakan selesaikan pembayaran terlebih dahulu.'],
            ]);
        }
    }
}
