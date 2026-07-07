<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Events\ServiceBookingMatched;
use App\Models\PatientAddress;
use App\Models\PatientMember;
use App\Models\PartnerService;
use App\Models\Payment;
use App\Models\Service;
use App\Models\ServiceBooking;
use App\Models\ServiceBookingHistory;
use App\Models\User;
use App\Services\BalanceService;
use App\Services\AppNotificationService;
use App\Services\MidtransService;
use App\Services\ServicePartnerSelectionService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class ServiceBookingController extends Controller
{
    public function __construct(
        private readonly ServicePartnerSelectionService $servicePartnerSelectionService,
        private readonly BalanceService $balanceService,
        private readonly AppNotificationService $notifications
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'patient_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'assigned_partner_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'service_id' => ['nullable', 'integer', 'exists:services,id'],
            'status' => ['nullable', 'in:pending,confirmed,scheduled,on_the_way,completed,cancelled'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $perPage = $this->resolvePerPage($request);

        $bookings = ServiceBooking::query()
            ->with(['service', 'patient', 'patientMember', 'assignedPartner.partnerProfile', 'address', 'histories.actor', 'payment'])
            ->when(
                $validated['patient_user_id'] ?? null,
                fn ($query, $patientId) => $query->where('patient_user_id', $patientId)
            )
            ->when(
                $validated['assigned_partner_user_id'] ?? null,
                fn ($query, $partnerId) => $query->where('assigned_partner_user_id', $partnerId)
            )
            ->when(
                $validated['service_id'] ?? null,
                fn ($query, $serviceId) => $query->where('service_id', $serviceId)
            )
            ->when(
                $validated['status'] ?? null,
                fn ($query, $status) => $query->where('status', $status)
            )
            ->latest()
            ->paginate($perPage)
            ->withQueryString();

        return response()->json([
            'message' => 'Daftar booking layanan berhasil diambil.',
            'data' => $bookings,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'service_id' => ['required', 'integer', 'exists:services,id'],
            'patient_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'patient_member_id' => ['nullable', 'integer', 'exists:patient_members,id'],
            'patient_address_id' => ['nullable', 'integer', 'exists:patient_addresses,id'],
            'booking_type' => ['nullable', 'in:scheduled,daily'],
            'scheduled_at' => ['nullable', 'date'],
            'schedule_start_at' => ['nullable', 'date'],
            'schedule_end_at' => ['nullable', 'date', 'after_or_equal:schedule_start_at'],
            'duration_days' => ['nullable', 'integer', 'min:1', 'max:30'],
            'notes' => ['nullable', 'string'],
        ]);

        $patientUserId = (int) ($validated['patient_user_id'] ?? $request->user()?->id);

        $this->ensureBookingCanBeCreatedByAuthenticatedUser($request, $patientUserId);
        $patientMember = isset($validated['patient_member_id'])
            ? $this->resolvePatientMember((int) $validated['patient_member_id'], $patientUserId)
            : null;

        $service = Service::query()
            ->with('partnerServices.partner.partnerProfile')
            ->findOrFail($validated['service_id']);

        if (! $service->is_active) {
            throw ValidationException::withMessages([
                'service_id' => ['Layanan yang dipilih sedang tidak aktif.'],
            ]);
        }

        if ($service->is_homecare && ! isset($validated['patient_address_id']) && ! $patientMember?->address) {
            throw ValidationException::withMessages([
                'patient_address_id' => ['Alamat pasien wajib diisi untuk layanan homecare. Kirim patient_address_id atau patient_member_id yang memiliki alamat.'],
            ]);
        }

        $address = $this->resolveBookingAddress($validated, $patientMember);

        $selectedPartnerService = $this->servicePartnerSelectionService
            ->resolveNearestPartnerForBooking($service, $address);

        $schedule = $this->resolveBookingSchedule($validated);
        $baseAmount = (float) ($selectedPartnerService->custom_price ?? $service->base_price ?? 0);
        $totalAmount = $baseAmount * $schedule['duration_days'];

        $booking = DB::transaction(function () use ($service, $patientUserId, $selectedPartnerService, $validated, $schedule, $totalAmount): ServiceBooking {
            $booking = ServiceBooking::create([
                'booking_code' => 'SVB-' . now()->format('YmdHis') . '-' . str_pad((string) random_int(1, 999), 3, '0', STR_PAD_LEFT),
                'service_id' => $service->id,
                'patient_user_id' => $patientUserId,
                'patient_member_id' => $validated['patient_member_id'] ?? null,
                'assigned_partner_user_id' => $selectedPartnerService->partner_user_id,
                'patient_address_id' => $validated['patient_address_id'] ?? null,
                'status' => 'pending',
                'booking_type' => $schedule['booking_type'],
                'scheduled_at' => $schedule['scheduled_at'],
                'schedule_start_at' => $schedule['schedule_start_at'],
                'schedule_end_at' => $schedule['schedule_end_at'],
                'duration_days' => $schedule['duration_days'],
                'total_amount' => $totalAmount,
                'subtotal' => $totalAmount,
                'notes' => $validated['notes'] ?? null,
            ]);

            Payment::create([
                'service_booking_id' => $booking->id,
                'patient_user_id' => $booking->patient_user_id,
                'payment_code' => 'PAY-SVC-' . now()->format('YmdHis') . '-' . str_pad((string) random_int(1, 999), 3, '0', STR_PAD_LEFT),
                'status' => 'pending',
                'amount' => $booking->total_amount,
                'notes' => 'Pembayaran layanan menunggu pelunasan sebelum mitra menerima pesanan.',
            ]);

            return $booking;
        });

        $booking->load(['service', 'patient', 'patientMember', 'assignedPartner.partnerProfile', 'address', 'histories.actor', 'payment']);

        $matchmaking = [
            'partner_service_id' => $selectedPartnerService->id,
            'partner_user_id' => $selectedPartnerService->partner_user_id,
            'distance_km' => $selectedPartnerService->distance_km,
            'match_score' => $selectedPartnerService->match_score,
            'quality_score' => $selectedPartnerService->quality_score,
        ];

        ServiceBookingMatched::dispatch($booking, $matchmaking);
        $this->notifications->send($booking->assigned_partner_user_id, [
            'type' => 'service_booking.matched',
            'title' => 'Pesanan layanan baru',
            'body' => 'Ada pesanan layanan baru yang cocok untuk Anda.',
            'action_url' => '/mitra/service-bookings/'.$booking->id,
            'reference_type' => 'service_booking',
            'reference_id' => $booking->id,
            'data' => [
                'service_booking_id' => $booking->id,
                'booking_code' => $booking->booking_code,
                'patient_user_id' => $booking->patient_user_id,
                'assigned_partner_user_id' => $booking->assigned_partner_user_id,
                'status' => $booking->status,
                'matchmaking' => $matchmaking,
            ],
        ]);

        return response()->json([
            'message' => 'Booking layanan berhasil dibuat.',
            'data' => $booking,
            'matchmaking' => $matchmaking,
        ], 201);
    }

    public function show(ServiceBooking $serviceBooking): JsonResponse
    {
        $serviceBooking->load(['service', 'patient', 'patientMember', 'assignedPartner.partnerProfile', 'address', 'histories.actor', 'partnerBalanceTransaction', 'payment']);

        return response()->json([
            'message' => 'Detail booking layanan berhasil diambil.',
            'data' => $serviceBooking,
        ]);
    }

    public function pay(Request $request, ServiceBooking $serviceBooking, MidtransService $midtransService): JsonResponse
    {
        $this->ensurePatientCanPayBooking($request, $serviceBooking);

        $validated = $request->validate([
            'notes' => ['nullable', 'string'],
        ]);

        $payment = $serviceBooking->payment;

        if (! $payment) {
            $payment = Payment::create([
                'service_booking_id' => $serviceBooking->id,
                'patient_user_id' => $serviceBooking->patient_user_id,
                'payment_code' => 'PAY-SVC-' . now()->format('YmdHis') . '-' . str_pad((string) random_int(1, 999), 3, '0', STR_PAD_LEFT),
                'status' => 'pending',
                'amount' => $serviceBooking->total_amount,
                'notes' => 'Pembayaran layanan dibuat ulang dari endpoint pay.',
            ]);
        }

        if ($payment->status === 'paid') {
            $serviceBooking->load(['service', 'patient', 'patientMember', 'assignedPartner.partnerProfile', 'address', 'payment']);

            return response()->json([
                'message' => 'Pembayaran layanan sudah lunas.',
                'data' => $serviceBooking,
            ]);
        }

        $payment->update([
            'notes' => $validated['notes'] ?? $payment->notes,
        ]);

        try {
            $snap = $midtransService->getOrCreateSnapTransaction($payment->fresh(['patient', 'serviceBooking.service']));
        } catch (Throwable $exception) {
            Log::error('Gagal membuat Snap token Midtrans untuk service booking.', [
                'service_booking_id' => $serviceBooking->id,
                'payment_id' => $payment->id,
                'payment_code' => $payment->payment_code,
                'error' => $exception->getMessage(),
            ]);

            throw ValidationException::withMessages([
                'payment' => [$exception->getMessage()],
            ]);
        }

        $serviceBooking->load(['service', 'patient', 'patientMember', 'assignedPartner.partnerProfile', 'address', 'payment']);

        return response()->json([
            'message' => $snap['is_reused']
                ? 'Snap token Midtrans lama masih aktif dan dipakai ulang untuk pembayaran layanan.'
                : 'Transaksi Midtrans berhasil dibuat. Lanjutkan pembayaran layanan.',
            'data' => [
                'service_booking' => $serviceBooking,
                'payment' => $payment->fresh(),
                'midtrans' => $snap,
            ],
        ]);
    }

    public function updateStatus(Request $request, ServiceBooking $serviceBooking): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:pending,confirmed,scheduled,on_the_way,completed,cancelled'],
            'scheduled_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $this->ensureBookingCanBeUpdatedByAuthenticatedUser($request, $serviceBooking);

        if (in_array($validated['status'], ['confirmed', 'scheduled', 'on_the_way', 'completed'], true)) {
            $this->ensureServiceBookingPaymentCompleted($serviceBooking);
        }

        $payload = [
            'status' => $validated['status'],
            'scheduled_at' => $validated['scheduled_at'] ?? $serviceBooking->scheduled_at,
            'notes' => $validated['notes'] ?? $serviceBooking->notes,
        ];

        if ($validated['status'] === 'on_the_way' && $serviceBooking->started_at === null) {
            $payload['started_at'] = now();
        }

        if (in_array($validated['status'], ['completed', 'cancelled'], true) && $serviceBooking->completed_at === null) {
            $payload['completed_at'] = now();
        }

        $serviceBooking->update($payload);
        $this->recordHistory(
            $serviceBooking,
            $request->user(),
            'status',
            'Status booking diperbarui',
            $validated['notes'] ?? null,
            ['status' => $validated['status']]
        );

        $serviceBooking->load(['service', 'patient', 'patientMember', 'assignedPartner.partnerProfile', 'address', 'histories.actor']);

        $actor = $request->user();
        $notificationTargetId = $actor?->id === $serviceBooking->patient_user_id
            ? $serviceBooking->assigned_partner_user_id
            : $serviceBooking->patient_user_id;

        if ($notificationTargetId) {
            $this->notifications->send($notificationTargetId, [
                'type' => 'service_booking.status_updated',
                'title' => 'Status layanan diperbarui',
                'body' => ($actor?->name ?? 'User').' memperbarui status pesanan layanan menjadi '.$serviceBooking->status.'.',
                'action_url' => $notificationTargetId === $serviceBooking->patient_user_id
                    ? '/patient/service-bookings/'.$serviceBooking->id
                    : '/mitra/service-bookings/'.$serviceBooking->id,
                'reference_type' => 'service_booking',
                'reference_id' => $serviceBooking->id,
                'data' => [
                    'service_booking_id' => $serviceBooking->id,
                    'booking_code' => $serviceBooking->booking_code,
                    'status' => $serviceBooking->status,
                ],
            ]);
        }

        return response()->json([
            'message' => 'Status booking layanan berhasil diperbarui.',
            'data' => $serviceBooking,
        ]);
    }

    public function accept(Request $request, ServiceBooking $serviceBooking): JsonResponse
    {
        $partner = $this->resolveAuthenticatedMedicalPartner($request);
        $this->ensurePartnerCanHandleBooking($partner, $serviceBooking);
        $this->ensureServiceBookingPaymentCompleted($serviceBooking);

        if (! in_array($serviceBooking->status, ['pending', 'scheduled'], true)) {
            throw ValidationException::withMessages([
                'status' => ['Booking hanya dapat diterima saat status pending atau scheduled.'],
            ]);
        }

        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $serviceBooking->update([
            'assigned_partner_user_id' => $serviceBooking->assigned_partner_user_id ?? $partner->id,
            'status' => 'confirmed',
            'accepted_at' => $serviceBooking->accepted_at ?? now(),
            'notes' => $validated['notes'] ?? $serviceBooking->notes,
        ]);

        $this->recordHistory(
            $serviceBooking,
            $partner,
            'status',
            'Pesanan diterima mitra',
            $validated['notes'] ?? 'Mitra menerima pesanan layanan pasien.',
            ['status' => 'confirmed']
        );

        $serviceBooking->load(['service', 'patient', 'patientMember', 'assignedPartner.partnerProfile', 'address', 'histories.actor']);

        $this->notifications->send($serviceBooking->patient_user_id, [
            'type' => 'service_booking.accepted',
            'title' => 'Pesanan layanan diterima',
            'body' => $partner->name.' menerima pesanan layanan Anda.',
            'action_url' => '/patient/service-bookings/'.$serviceBooking->id,
            'reference_type' => 'service_booking',
            'reference_id' => $serviceBooking->id,
            'data' => [
                'service_booking_id' => $serviceBooking->id,
                'booking_code' => $serviceBooking->booking_code,
                'partner_user_id' => $partner->id,
                'status' => $serviceBooking->status,
            ],
        ]);

        return response()->json([
            'message' => 'Pesanan layanan berhasil diterima.',
            'data' => $serviceBooking,
        ]);
    }

    public function startJourney(Request $request, ServiceBooking $serviceBooking): JsonResponse
    {
        $partner = $this->resolveAuthenticatedMedicalPartner($request);
        $this->ensureAssignedPartner($partner, $serviceBooking);
        $this->ensureServiceBookingPaymentCompleted($serviceBooking);

        if (! in_array($serviceBooking->status, ['confirmed', 'scheduled'], true)) {
            throw ValidationException::withMessages([
                'status' => ['Mitra hanya dapat berangkat setelah pesanan diterima.'],
            ]);
        }

        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $serviceBooking->update([
            'status' => 'on_the_way',
            'started_at' => $serviceBooking->started_at ?? now(),
            'notes' => $validated['notes'] ?? $serviceBooking->notes,
        ]);

        $this->recordHistory(
            $serviceBooking,
            $partner,
            'status',
            'Mitra menuju lokasi pasien',
            $validated['notes'] ?? 'Mitra sedang dalam perjalanan ke alamat pasien.',
            ['status' => 'on_the_way']
        );

        $serviceBooking->load(['service', 'patient', 'patientMember', 'assignedPartner.partnerProfile', 'address', 'histories.actor']);

        $this->notifications->send($serviceBooking->patient_user_id, [
            'type' => 'service_booking.on_the_way',
            'title' => 'Mitra menuju lokasi',
            'body' => $partner->name.' sedang menuju lokasi Anda.',
            'action_url' => '/patient/service-bookings/'.$serviceBooking->id,
            'reference_type' => 'service_booking',
            'reference_id' => $serviceBooking->id,
            'data' => [
                'service_booking_id' => $serviceBooking->id,
                'booking_code' => $serviceBooking->booking_code,
                'partner_user_id' => $partner->id,
                'status' => $serviceBooking->status,
            ],
        ]);

        return response()->json([
            'message' => 'Status perjalanan mitra berhasil diperbarui.',
            'data' => $serviceBooking,
        ]);
    }

    public function addTreatmentHistory(Request $request, ServiceBooking $serviceBooking): JsonResponse
    {
        $partner = $this->resolveAuthenticatedMedicalPartner($request);
        $this->ensureAssignedPartner($partner, $serviceBooking);
        $this->ensureServiceBookingPaymentCompleted($serviceBooking);

        if (! in_array($serviceBooking->status, ['confirmed', 'on_the_way'], true)) {
            throw ValidationException::withMessages([
                'status' => ['Catatan penanganan hanya dapat ditambahkan sebelum pesanan selesai.'],
            ]);
        }

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'handled_at' => ['nullable', 'date'],
            'meta' => ['nullable', 'array'],
        ]);

        $history = $this->recordHistory(
            $serviceBooking,
            $partner,
            'treatment',
            $validated['title'],
            $validated['description'] ?? null,
            $validated['meta'] ?? null,
            isset($validated['handled_at']) ? Carbon::parse($validated['handled_at']) : now()
        );

        $history->load('actor');

        return response()->json([
            'message' => 'Catatan penanganan berhasil ditambahkan.',
            'data' => $history,
        ], 201);
    }

    public function complete(Request $request, ServiceBooking $serviceBooking): JsonResponse
    {
        $partner = $this->resolveAuthenticatedMedicalPartner($request);
        $this->ensureAssignedPartner($partner, $serviceBooking);
        $this->ensureServiceBookingPaymentCompleted($serviceBooking);

        if (! in_array($serviceBooking->status, ['confirmed', 'on_the_way'], true)) {
            throw ValidationException::withMessages([
                'status' => ['Pesanan hanya dapat diselesaikan setelah diterima atau mitra berangkat.'],
            ]);
        }

        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:1000'],
            'summary' => ['nullable', 'string'],
        ]);

        $serviceBooking = DB::transaction(function () use ($serviceBooking, $partner, $validated): ServiceBooking {
            $lockedBooking = ServiceBooking::lockForUpdate()->findOrFail($serviceBooking->id);

            if ($lockedBooking->status === 'completed') {
                throw ValidationException::withMessages([
                    'status' => ['Pesanan layanan sudah selesai.'],
                ]);
            }

            $lockedBooking->update([
                'status' => 'completed',
                'completed_at' => $lockedBooking->completed_at ?? now(),
                'notes' => $validated['notes'] ?? $lockedBooking->notes,
            ]);

            $this->recordHistory(
                $lockedBooking,
                $partner,
                'status',
                'Pesanan layanan selesai',
                $validated['summary'] ?? 'Mitra menyelesaikan layanan pasien.',
                ['status' => 'completed']
            );

            if ((float) $lockedBooking->total_amount > 0 && $lockedBooking->partner_balance_transaction_id === null) {
                $balance = $this->balanceService->getOrCreateBalance($partner);
                $transaction = $this->balanceService->credit($balance, (float) $lockedBooking->total_amount, [
                    'reference_type' => 'service_booking',
                    'reference_id' => $lockedBooking->id,
                    'booking_code' => $lockedBooking->booking_code,
                    'description' => 'Pendapatan layanan '.$lockedBooking->booking_code,
                ]);

                $lockedBooking->update([
                    'partner_paid_at' => now(),
                    'partner_balance_transaction_id' => $transaction->id,
                ]);
            }

            return $lockedBooking;
        });

        $serviceBooking->load(['service', 'patient', 'patientMember', 'assignedPartner.partnerProfile', 'address', 'histories.actor', 'partnerBalanceTransaction']);

        $this->notifications->send($serviceBooking->patient_user_id, [
            'type' => 'service_booking.completed',
            'title' => 'Pesanan layanan selesai',
            'body' => 'Pesanan layanan '.$serviceBooking->booking_code.' telah diselesaikan.',
            'action_url' => '/patient/service-bookings/'.$serviceBooking->id,
            'reference_type' => 'service_booking',
            'reference_id' => $serviceBooking->id,
            'data' => [
                'service_booking_id' => $serviceBooking->id,
                'booking_code' => $serviceBooking->booking_code,
                'partner_user_id' => $partner->id,
                'status' => $serviceBooking->status,
            ],
        ]);

        return response()->json([
            'message' => 'Pesanan layanan berhasil diselesaikan dan saldo mitra diperbarui.',
            'data' => $serviceBooking,
        ]);
    }

    private function ensureBookingCanBeCreatedByAuthenticatedUser(Request $request, int $patientUserId): void
    {
        /** @var User|null $user */
        $user = $request->user();

        if (! $user) {
            throw ValidationException::withMessages([
                'user' => ['User login tidak ditemukan.'],
            ]);
        }

        if ($user->id !== $patientUserId && $user->role !== 'admin') {
            throw ValidationException::withMessages([
                'patient_user_id' => ['Booking layanan hanya dapat dibuat untuk akun pasien yang sedang login.'],
            ]);
        }
    }

    private function ensurePatientCanPayBooking(Request $request, ServiceBooking $serviceBooking): void
    {
        /** @var User|null $user */
        $user = $request->user();

        if (! $user || $serviceBooking->patient_user_id !== $user->id) {
            throw ValidationException::withMessages([
                'service_booking' => ['Pembayaran hanya dapat dilakukan oleh pasien pemilik booking.'],
            ]);
        }
    }

    private function resolvePatientMember(int $patientMemberId, int $ownerUserId): PatientMember
    {
        return PatientMember::query()
            ->where('owner_user_id', $ownerUserId)
            ->findOrFail($patientMemberId);
    }

    private function resolveBookingAddress(array $validated, ?PatientMember $patientMember): ?PatientAddress
    {
        if (isset($validated['patient_address_id'])) {
            return PatientAddress::find($validated['patient_address_id']);
        }

        if (! $patientMember || ! $patientMember->address) {
            return null;
        }

        return $patientMember->toPatientAddress();
    }

    private function ensureBookingCanBeUpdatedByAuthenticatedUser(Request $request, ServiceBooking $serviceBooking): void
    {
        /** @var User|null $user */
        $user = $request->user();

        if (! $user) {
            throw ValidationException::withMessages([
                'user' => ['User login tidak ditemukan.'],
            ]);
        }

        if (! in_array($user->id, [$serviceBooking->patient_user_id, $serviceBooking->assigned_partner_user_id], true) && $user->role !== 'admin') {
            throw ValidationException::withMessages([
                'user' => ['Anda tidak memiliki akses untuk memperbarui booking layanan ini.'],
            ]);
        }
    }

    private function resolveAuthenticatedMedicalPartner(Request $request): User
    {
        /** @var User|null $user */
        $user = $request->user();

        if (! $user) {
            throw ValidationException::withMessages([
                'user' => ['User login tidak ditemukan.'],
            ]);
        }

        $user->loadMissing('partnerProfile');

        if ($user->role !== 'mitra' || ! $user->partnerProfile) {
            throw ValidationException::withMessages([
                'user' => ['Endpoint ini hanya dapat diakses oleh mitra layanan kesehatan.'],
            ]);
        }

        return $user;
    }

    private function ensureServiceBookingPaymentCompleted(ServiceBooking $serviceBooking): void
    {
        $serviceBooking->loadMissing('payment');

        if (! $serviceBooking->payment) {
            return;
        }

        if ($serviceBooking->payment->status !== 'paid') {
            throw ValidationException::withMessages([
                'payment' => ['Pesanan layanan belum dapat diproses karena pembayaran belum lunas.'],
            ]);
        }
    }

    private function resolveBookingSchedule(array $validated): array
    {
        $bookingType = $validated['booking_type'] ?? 'scheduled';
        $scheduledAt = isset($validated['scheduled_at']) ? Carbon::parse($validated['scheduled_at']) : null;

        if ($scheduledAt && $scheduledAt->lessThanOrEqualTo(now())) {
            throw ValidationException::withMessages([
                'scheduled_at' => ['Jadwal layanan harus setelah waktu sekarang.'],
            ]);
        }

        if ($bookingType === 'daily') {
            $startAt = isset($validated['schedule_start_at'])
                ? Carbon::parse($validated['schedule_start_at'])
                : $scheduledAt;

            if (! $startAt) {
                throw ValidationException::withMessages([
                    'schedule_start_at' => ['Tanggal mulai wajib diisi untuk booking harian.'],
                ]);
            }

            if ($startAt->lessThanOrEqualTo(now())) {
                throw ValidationException::withMessages([
                    'schedule_start_at' => ['Tanggal mulai harus setelah waktu sekarang.'],
                ]);
            }

            $durationDays = (int) ($validated['duration_days'] ?? 1);
            $endAt = isset($validated['schedule_end_at'])
                ? Carbon::parse($validated['schedule_end_at'])
                : $startAt->copy()->addDays($durationDays - 1);

            if ($endAt->lessThan($startAt)) {
                throw ValidationException::withMessages([
                    'schedule_end_at' => ['Tanggal selesai tidak boleh sebelum tanggal mulai.'],
                ]);
            }

            $durationDays = max(1, $startAt->copy()->startOfDay()->diffInDays($endAt->copy()->startOfDay()) + 1);

            if ($durationDays > 30) {
                throw ValidationException::withMessages([
                    'duration_days' => ['Booking harian maksimal 30 hari.'],
                ]);
            }

            return [
                'booking_type' => 'daily',
                'scheduled_at' => $startAt,
                'schedule_start_at' => $startAt,
                'schedule_end_at' => $endAt,
                'duration_days' => $durationDays,
            ];
        }

        return [
            'booking_type' => 'scheduled',
            'scheduled_at' => $scheduledAt,
            'schedule_start_at' => $scheduledAt,
            'schedule_end_at' => $scheduledAt,
            'duration_days' => 1,
        ];
    }

    private function ensurePartnerCanHandleBooking(User $partner, ServiceBooking $serviceBooking): void
    {
        $serviceBooking->loadMissing('service');

        if ($serviceBooking->assigned_partner_user_id !== null && $serviceBooking->assigned_partner_user_id !== $partner->id) {
            throw ValidationException::withMessages([
                'service_booking' => ['Booking ini sudah ditugaskan ke mitra lain.'],
            ]);
        }

        $allowedServiceTypes = match ($partner->partnerProfile->profession) {
            'dokter' => ['dokter_homecare', 'konsultasi_tindakan'],
            'perawat' => ['perawat_homecare', 'konsultasi_tindakan'],
            'bidan' => ['bidan_homecare', 'konsultasi_tindakan'],
            default => [],
        };

        if (! in_array($serviceBooking->service->service_type, $allowedServiceTypes, true)) {
            throw ValidationException::withMessages([
                'service_id' => ['Layanan booking ini tidak sesuai dengan profesi mitra.'],
            ]);
        }

        $hasActiveService = PartnerService::query()
            ->where('partner_user_id', $partner->id)
            ->where('service_id', $serviceBooking->service_id)
            ->where('is_active', true)
            ->where('is_verified', true)
            ->exists();

        if (! $hasActiveService) {
            throw ValidationException::withMessages([
                'service_id' => ['Mitra belum memiliki layanan aktif dan terverifikasi untuk booking ini.'],
            ]);
        }
    }

    private function ensureAssignedPartner(User $partner, ServiceBooking $serviceBooking): void
    {
        if ($serviceBooking->assigned_partner_user_id !== $partner->id) {
            throw ValidationException::withMessages([
                'service_booking' => ['Booking ini bukan milik mitra yang sedang login.'],
            ]);
        }
    }

    private function recordHistory(
        ServiceBooking $serviceBooking,
        ?User $actor,
        string $type,
        string $title,
        ?string $description = null,
        ?array $meta = null,
        mixed $handledAt = null
    ): ServiceBookingHistory {
        return ServiceBookingHistory::create([
            'service_booking_id' => $serviceBooking->id,
            'actor_user_id' => $actor?->id,
            'type' => $type,
            'title' => $title,
            'description' => $description,
            'meta' => $meta,
            'handled_at' => $handledAt ?? now(),
        ]);
    }
}
