<?php

namespace App\Http\Controllers\Api\Mitra;

use App\Events\ServiceBookingPartnerLocationUpdated;
use App\Events\ServiceBookingMatched;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\PartnerService;
use App\Models\Service;
use App\Models\ServiceBooking;
use App\Models\ServiceBookingHistory;
use App\Models\User;
use App\Services\AppNotificationService;
use App\Services\BalanceService;
use App\Services\ServiceBookingFeeCalculator;
use App\Services\ServicePartnerSelectionService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ServiceBookingController extends Controller
{
    public function __construct(
        private readonly BalanceService $balanceService,
        private readonly AppNotificationService $notifications,
        private readonly ServicePartnerSelectionService $servicePartnerSelectionService,
        private readonly ServiceBookingFeeCalculator $feeCalculator
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $partner = $this->resolveAuthenticatedMedicalPartner($request);

        $validated = $request->validate([
            'service_id' => ['nullable', 'integer', 'exists:services,id'],
            'status' => ['nullable', 'in:pending,confirmed,scheduled,on_the_way,completed,cancelled'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $bookings = ServiceBooking::query()
            ->with(['service', 'patient', 'patientMember', 'assignedPartner.partnerProfile', 'address', 'histories.actor', 'payment'])
            ->where('assigned_partner_user_id', $partner->id)
            ->when(
                $validated['service_id'] ?? null,
                fn ($query, $serviceId) => $query->where('service_id', $serviceId)
            )
            ->when(
                $validated['status'] ?? null,
                fn ($query, $status) => $query->where('status', $status)
            )
            ->latest()
            ->paginate($request->input('per_page', 20))
            ->withQueryString();

        $bookings->getCollection()->each(function (ServiceBooking $booking) {
            $booking->useServiceAddressRelation();
            $this->normalizeMitraBookingAmounts($booking);
        });

        return response()->json([
            'message' => 'Daftar booking layanan mitra berhasil diambil.',
            'data' => $bookings,
        ]);
    }

    public function show(Request $request, ServiceBooking $serviceBooking): JsonResponse
    {
        $partner = $this->resolveAuthenticatedMedicalPartner($request);
        $this->ensureAssignedPartner($partner, $serviceBooking);

        $serviceBooking->load(['service', 'patient', 'patientMember', 'assignedPartner.partnerProfile', 'address', 'histories.actor', 'partnerBalanceTransaction', 'payment']);
        $serviceBooking->useServiceAddressRelation();
        $this->normalizeMitraBookingAmounts($serviceBooking);
        $this->attachDetailBookingActions($serviceBooking);

        return response()->json([
            'message' => 'Detail booking layanan mitra berhasil diambil.',
            'data' => $serviceBooking,
        ]);
    }

    public function accept(Request $request, ServiceBooking $serviceBooking): JsonResponse
    {
        $partner = $this->resolveAuthenticatedMedicalPartner($request);
        $this->ensurePartnerCanHandleBooking($partner, $serviceBooking);

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
        $serviceBooking->useServiceAddressRelation();
        $this->normalizeMitraBookingAmounts($serviceBooking);

        $this->notifications->send($serviceBooking->patient_user_id, [
            'type' => 'service_booking.accepted',
            'title' => 'Pesanan layanan diterima',
            'body' => $partner->name.' menerima pesanan layanan Anda. Silakan lanjutkan pembayaran agar mitra dapat berangkat.',
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

    public function reject(Request $request, ServiceBooking $serviceBooking): JsonResponse
    {
        $partner = $this->resolveAuthenticatedMedicalPartner($request);
        $this->ensureAssignedPartner($partner, $serviceBooking);

        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $rematch = null;
        $serviceBooking = DB::transaction(function () use ($serviceBooking, $partner, $validated, &$rematch): ServiceBooking {
            $lockedBooking = ServiceBooking::query()
                ->with(['service.partnerServices.partner.partnerProfile', 'patientMember', 'address', 'histories', 'payment'])
                ->lockForUpdate()
                ->findOrFail($serviceBooking->id);
            $payment = $lockedBooking->payment()->lockForUpdate()->first();

            if (! in_array($lockedBooking->status, ['pending', 'scheduled'], true)) {
                throw ValidationException::withMessages([
                    'status' => ['Pesanan hanya dapat ditolak sebelum diterima mitra.'],
                ]);
            }

            if ($lockedBooking->accepted_at !== null) {
                throw ValidationException::withMessages([
                    'service_booking' => ['Pesanan yang sudah diterima tidak dapat ditolak dari flow ini.'],
                ]);
            }

            if ($payment && $payment->status === 'paid') {
                throw ValidationException::withMessages([
                    'payment' => ['Pesanan yang sudah dibayar tidak dapat ditolak langsung. Gunakan proses pembatalan/refund.'],
                ]);
            }

            $rejectedPartnerIds = $this->rejectedPartnerUserIds($lockedBooking);
            $rejectedPartnerIds[] = $partner->id;

            $this->recordHistory(
                $lockedBooking,
                $partner,
                'status',
                'Mitra menolak pesanan',
                $validated['notes'] ?? 'Mitra menolak pesanan layanan.',
                [
                    'status' => 'rejected_by_partner',
                    'type' => 'matchmaking',
                    'rejected_partner_user_id' => $partner->id,
                    'previous_assigned_partner_user_id' => $lockedBooking->assigned_partner_user_id,
                ]
            );

            try {
                $selectedPartnerService = $this->servicePartnerSelectionService
                    ->resolveNearestPartnerForBooking($lockedBooking->service, $lockedBooking->serviceAddress(), $rejectedPartnerIds);

                $fees = $this->feeCalculator->calculate([
                    'visit_plan' => $lockedBooking->visit_plan ?? 'once',
                    'visit_count' => max(1, (int) ($lockedBooking->visit_count ?? 1)),
                    'care_mode' => $lockedBooking->care_mode ?? 'visit',
                    'location_type' => $lockedBooking->location_type ?? 'home',
                    'distance_km' => $selectedPartnerService->distance_km,
                ]);
                $finalPrice = (float) $lockedBooking->subtotal
                    - (float) $lockedBooking->discount_amount
                    + $fees['transport_fee']
                    + $fees['meal_fee'];

                $lockedBooking->update([
                    'assigned_partner_user_id' => $selectedPartnerService->partner_user_id,
                    'accepted_at' => null,
                    'distance_km' => $selectedPartnerService->distance_km,
                    'transport_fee' => $fees['transport_fee'],
                    'meal_fee' => $fees['meal_fee'],
                    'fee_policy_snapshot' => $fees['policy_snapshot'],
                    'total_amount' => $finalPrice,
                    'status' => 'pending',
                ]);

                $this->refreshPendingPaymentAfterMatchmaking($lockedBooking, $payment, $finalPrice, 'Transaksi pembayaran dibuat/diperbarui setelah rematch mitra.');

                $rematch = [
                    'partner_service_id' => $selectedPartnerService->id,
                    'partner_user_id' => $selectedPartnerService->partner_user_id,
                    'distance_km' => $selectedPartnerService->distance_km,
                    'match_score' => $selectedPartnerService->match_score,
                    'quality_score' => $selectedPartnerService->quality_score,
                    'rematched_from_partner_user_id' => $partner->id,
                ];

                $this->recordHistory(
                    $lockedBooking,
                    null,
                    'status',
                    'Pesanan dialihkan ke mitra lain',
                    'Sistem otomatis mencari mitra pengganti setelah penolakan.',
                    [
                        'status' => 'rematched',
                        'type' => 'matchmaking',
                        'new_partner_user_id' => $selectedPartnerService->partner_user_id,
                        'rejected_partner_user_id' => $partner->id,
                    ]
                );

                return $lockedBooking->fresh(['service', 'patient', 'patientMember', 'assignedPartner.partnerProfile', 'address', 'histories.actor', 'payment']);
            } catch (ValidationException) {
                $fees = $this->feeCalculator->calculate([
                    'visit_plan' => $lockedBooking->visit_plan ?? 'once',
                    'visit_count' => max(1, (int) ($lockedBooking->visit_count ?? 1)),
                    'care_mode' => $lockedBooking->care_mode ?? 'visit',
                    'location_type' => $lockedBooking->location_type ?? 'home',
                    'distance_km' => null,
                ]);
                $finalPrice = (float) $lockedBooking->subtotal
                    - (float) $lockedBooking->discount_amount
                    + $fees['transport_fee']
                    + $fees['meal_fee'];

                $lockedBooking->update([
                    'assigned_partner_user_id' => null,
                    'accepted_at' => null,
                    'distance_km' => null,
                    'transport_fee' => $fees['transport_fee'],
                    'meal_fee' => $fees['meal_fee'],
                    'fee_policy_snapshot' => $fees['policy_snapshot'],
                    'total_amount' => $finalPrice,
                    'status' => 'pending',
                ]);

                $this->deletePendingPaymentAfterMatchmakingFailure($payment);

                $rematch = null;

                $this->recordHistory(
                    $lockedBooking,
                    null,
                    'status',
                    'Belum ada mitra pengganti',
                    'Semua kandidat tersedia sudah menolak atau belum ada mitra aktif lain.',
                    ['status' => 'waiting_partner_available', 'type' => 'matchmaking']
                );

                return $lockedBooking->fresh(['service', 'patient', 'patientMember', 'assignedPartner.partnerProfile', 'address', 'histories.actor', 'payment']);
            }
        });

        $serviceBooking->useServiceAddressRelation();
        $this->normalizeMitraBookingAmounts($serviceBooking);

        if ($rematch) {
            ServiceBookingMatched::dispatch($serviceBooking, $rematch);

            $this->notifications->send($serviceBooking->assigned_partner_user_id, [
                'type' => 'service_booking.matched',
                'title' => 'Pesanan layanan baru',
                'body' => 'Ada pesanan layanan baru yang dialihkan ke Anda. Terima pesanan, lalu tunggu pasien menyelesaikan pembayaran.',
                'action_url' => '/mitra/service-bookings/'.$serviceBooking->id,
                'reference_type' => 'service_booking',
                'reference_id' => $serviceBooking->id,
                'data' => [
                    'service_booking_id' => $serviceBooking->id,
                    'booking_code' => $serviceBooking->booking_code,
                    'patient_user_id' => $serviceBooking->patient_user_id,
                    'assigned_partner_user_id' => $serviceBooking->assigned_partner_user_id,
                    'status' => $serviceBooking->status,
                    'payment_status' => $serviceBooking->payment?->status,
                    'matchmaking' => $rematch,
                ],
            ]);
        }

        $this->notifications->send($serviceBooking->patient_user_id, [
            'type' => $rematch ? 'service_booking.rematched' : 'service_booking.waiting_partner',
            'title' => $rematch ? 'Mitra pengganti ditemukan' : 'Sedang mencari mitra pengganti',
            'body' => $rematch
                ? 'Pesanan layanan '.$serviceBooking->booking_code.' sudah dialihkan ke mitra lain.'
                : 'Mitra sebelumnya menolak pesanan '.$serviceBooking->booking_code.'. Sistem akan mencari mitra lain yang tersedia.',
            'action_url' => '/patient/service-bookings/'.$serviceBooking->id,
            'reference_type' => 'service_booking',
            'reference_id' => $serviceBooking->id,
            'data' => [
                'service_booking_id' => $serviceBooking->id,
                'booking_code' => $serviceBooking->booking_code,
                'status' => $serviceBooking->status,
                'assigned_partner_user_id' => $serviceBooking->assigned_partner_user_id,
            ],
        ]);

        return response()->json([
            'message' => $rematch
                ? 'Pesanan ditolak dan berhasil dialihkan ke mitra lain.'
                : 'Pesanan ditolak. Belum ada mitra pengganti yang tersedia.',
            'data' => $serviceBooking,
            'matchmaking' => $rematch,
            'matchmaking_status' => $rematch ? 'rematched_waiting_partner_acceptance' : 'waiting_partner_available',
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
        $serviceBooking->useServiceAddressRelation();
        $this->normalizeMitraBookingAmounts($serviceBooking);

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

    public function updateLocation(Request $request, ServiceBooking $serviceBooking): JsonResponse
    {
        $partner = $this->resolveAuthenticatedMedicalPartner($request);
        $this->ensureAssignedPartner($partner, $serviceBooking);

        if ($serviceBooking->status !== 'on_the_way') {
            throw ValidationException::withMessages([
                'status' => ['Lokasi realtime hanya dapat dikirim saat mitra sedang menuju lokasi pasien.'],
            ]);
        }

        $validated = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy_meters' => ['nullable', 'numeric', 'min:0', 'max:10000'],
            'heading' => ['nullable', 'numeric', 'min:0', 'max:360'],
            'speed_mps' => ['nullable', 'numeric', 'min:0', 'max:200'],
            'recorded_at' => ['nullable', 'date'],
        ]);

        $location = $serviceBooking->partnerLocation()->updateOrCreate(
            ['service_booking_id' => $serviceBooking->id],
            [
                'partner_user_id' => $partner->id,
                'latitude' => $validated['latitude'],
                'longitude' => $validated['longitude'],
                'accuracy_meters' => $validated['accuracy_meters'] ?? null,
                'heading' => $validated['heading'] ?? null,
                'speed_mps' => $validated['speed_mps'] ?? null,
                'recorded_at' => isset($validated['recorded_at'])
                    ? Carbon::parse($validated['recorded_at'])
                    : now(),
            ]
        );

        $serviceBooking->setRelation('partnerLocation', $location);

        ServiceBookingPartnerLocationUpdated::dispatch($serviceBooking);

        return response()->json([
            'message' => 'Lokasi realtime mitra berhasil diperbarui.',
            'data' => [
                'service_booking_id' => $serviceBooking->id,
                'status' => $serviceBooking->status,
                'location' => [
                    'latitude' => $location->latitude,
                    'longitude' => $location->longitude,
                    'accuracy_meters' => $location->accuracy_meters,
                    'heading' => $location->heading,
                    'speed_mps' => $location->speed_mps,
                    'updated_at' => $location->recorded_at?->toISOString(),
                ],
            ],
        ]);
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
            $payment = $lockedBooking->payment()->lockForUpdate()->first();

            if ($lockedBooking->status === 'completed') {
                throw ValidationException::withMessages([
                    'status' => ['Pesanan layanan sudah selesai.'],
                ]);
            }

            if (! $payment || $payment->status !== 'paid') {
                throw ValidationException::withMessages([
                    'payment' => ['Pesanan hanya dapat diselesaikan setelah pembayaran lunas.'],
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

            $partnerPayoutAmount = $lockedBooking->partnerPayoutAmount();

            if ($partnerPayoutAmount > 0 && $lockedBooking->partner_balance_transaction_id === null) {
                $balance = $this->balanceService->getOrCreateBalance($partner);
                $transaction = $this->balanceService->credit($balance, $partnerPayoutAmount, [
                    'reference_type' => 'service_booking',
                    'reference_id' => $lockedBooking->id,
                    'booking_code' => $lockedBooking->booking_code,
                    'patient_paid_amount' => (float) $lockedBooking->total_amount,
                    'partner_payout_amount' => $partnerPayoutAmount,
                    'idempotency_key' => 'service_booking:'.$lockedBooking->id.':partner_payout',
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
        $serviceBooking->useServiceAddressRelation();
        $this->normalizeMitraBookingAmounts($serviceBooking);

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

    public function updateStatus(Request $request, ServiceBooking $serviceBooking): JsonResponse
    {
        $partner = $this->resolveAuthenticatedMedicalPartner($request);
        $this->ensureAssignedPartner($partner, $serviceBooking);

        $validated = $request->validate([
            'status' => ['required', 'in:pending,confirmed,scheduled,on_the_way,completed,cancelled'],
            'scheduled_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $serviceBooking = DB::transaction(function () use ($serviceBooking, $partner, $validated): ServiceBooking {
            $lockedBooking = ServiceBooking::query()->lockForUpdate()->findOrFail($serviceBooking->id);
            $payment = $lockedBooking->payment()->lockForUpdate()->first();
            $target = $validated['status'];

            if ($target === 'completed') {
                throw ValidationException::withMessages([
                    'status' => ['Gunakan endpoint complete agar penyelesaian dan payout diproses secara atomik.'],
                ]);
            }

            if (in_array($lockedBooking->status, ['completed', 'cancelled'], true)) {
                throw ValidationException::withMessages([
                    'status' => ['Status booking final tidak dapat diubah kembali.'],
                ]);
            }

            if ($target === 'cancelled' && ($payment?->status === 'paid' || $lockedBooking->partner_balance_transaction_id !== null)) {
                throw ValidationException::withMessages([
                    'status' => ['Booking yang sudah dibayar atau sudah payout tidak dapat dibatalkan langsung. Gunakan proses refund.'],
                ]);
            }

            $allowed = [
                'pending' => ['confirmed', 'cancelled'],
                'confirmed' => ['scheduled', 'on_the_way', 'cancelled'],
                'scheduled' => ['on_the_way', 'cancelled'],
                'on_the_way' => [],
            ];

            if ($target !== $lockedBooking->status && ! in_array($target, $allowed[$lockedBooking->status] ?? [], true)) {
                throw ValidationException::withMessages(['status' => ['Transisi status booking tidak diizinkan.']]);
            }

            if (in_array($target, ['scheduled', 'on_the_way'], true) && $payment?->status !== 'paid') {
                throw ValidationException::withMessages(['payment' => ['Pesanan belum dapat diproses karena pembayaran belum lunas.']]);
            }

            $lockedBooking->update([
                'status' => $target,
                'scheduled_at' => $validated['scheduled_at'] ?? $lockedBooking->scheduled_at,
                'notes' => $validated['notes'] ?? $lockedBooking->notes,
                'started_at' => $target === 'on_the_way' ? ($lockedBooking->started_at ?? now()) : $lockedBooking->started_at,
                'completed_at' => $target === 'cancelled' ? ($lockedBooking->completed_at ?? now()) : $lockedBooking->completed_at,
            ]);

            $this->recordHistory($lockedBooking, $partner, 'status', 'Status booking diperbarui', $validated['notes'] ?? null, ['status' => $target]);

            return $lockedBooking;
        });

        $serviceBooking->load(['service', 'patient', 'patientMember', 'assignedPartner.partnerProfile', 'address', 'histories.actor']);
        $serviceBooking->useServiceAddressRelation();
        $this->normalizeMitraBookingAmounts($serviceBooking);

        $this->notifications->send($serviceBooking->patient_user_id, [
            'type' => 'service_booking.status_updated',
            'title' => 'Status layanan diperbarui',
            'body' => $partner->name.' memperbarui status pesanan layanan menjadi '.$serviceBooking->status.'.',
            'action_url' => '/patient/service-bookings/'.$serviceBooking->id,
            'reference_type' => 'service_booking',
            'reference_id' => $serviceBooking->id,
            'data' => [
                'service_booking_id' => $serviceBooking->id,
                'booking_code' => $serviceBooking->booking_code,
                'status' => $serviceBooking->status,
            ],
        ]);

        return response()->json([
            'message' => 'Status booking layanan berhasil diperbarui.',
            'data' => $serviceBooking,
        ]);
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

    private function normalizeMitraBookingAmounts(ServiceBooking $serviceBooking): void
    {
        $patientTotalAmount = (float) $serviceBooking->getRawOriginal('total_amount');
        $payoutBreakdown = $serviceBooking->partnerPayoutBreakdown();
        $partnerPayoutAmount = $payoutBreakdown['partner_payout_amount'];

        $serviceBooking->setAttribute('patient_total_amount', $patientTotalAmount);
        $serviceBooking->setAttribute('partner_payout_amount', $partnerPayoutAmount);
        $serviceBooking->setAttribute('partner_payout_breakdown', $payoutBreakdown);
        $serviceBooking->setAttribute('total_amount', $partnerPayoutAmount);
    }

    private function rejectedPartnerUserIds(ServiceBooking $serviceBooking): array
    {
        $serviceBooking->loadMissing('histories');

        return $serviceBooking->histories
            ->map(fn (ServiceBookingHistory $history) => $history->meta['rejected_partner_user_id'] ?? null)
            ->filter()
            ->map(fn ($partnerUserId) => (int) $partnerUserId)
            ->unique()
            ->values()
            ->all();
    }

    private function refreshPendingPaymentAfterMatchmaking(ServiceBooking $serviceBooking, ?Payment $payment, float $amount, string $note): Payment
    {
        if ($payment && $payment->status === 'pending') {
            $payment->update([
                'amount' => $amount,
                'snap_token' => null,
                'snap_redirect_url' => null,
                'snap_token_created_at' => null,
                'notes' => trim(($payment->notes ? $payment->notes."\n" : '').$note),
            ]);

            $serviceBooking->setRelation('payment', $payment->fresh());

            return $serviceBooking->payment;
        }

        $payment = Payment::create([
            'service_booking_id' => $serviceBooking->id,
            'patient_user_id' => $serviceBooking->patient_user_id,
            'payment_code' => 'PAY-SVC-' . now()->format('YmdHis') . '-' . str_pad((string) random_int(1, 999), 3, '0', STR_PAD_LEFT),
            'status' => 'pending',
            'amount' => $amount,
            'notes' => $note,
        ]);

        $serviceBooking->setRelation('payment', $payment);

        return $payment;
    }

    private function deletePendingPaymentAfterMatchmakingFailure(?Payment $payment): void
    {
        if ($payment && $payment->status === 'pending') {
            $payment->delete();
        }
    }

    private function ensurePartnerCanHandleBooking(User $partner, ServiceBooking $serviceBooking): void
    {
        $serviceBooking->loadMissing('service.serviceCategory');

        if ($serviceBooking->assigned_partner_user_id !== null && $serviceBooking->assigned_partner_user_id !== $partner->id) {
            throw ValidationException::withMessages([
                'service_booking' => ['Booking ini sudah ditugaskan ke mitra lain.'],
            ]);
        }

        if (! $this->serviceMatchesPartnerProfession($serviceBooking->service, $partner->partnerProfile->profession)) {
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

    private function serviceMatchesPartnerProfession(Service $service, string $profession): bool
    {
        $categoryKey = strtolower((string) ($service->serviceCategory?->slug ?? $service->serviceCategory?->name ?? ''));

        if ($categoryKey !== '') {
            $allowedCategoryKeywords = match ($profession) {
                'dokter' => ['doctor', 'dokter'],
                'perawat' => ['nurse', 'perawat', 'caregiver'],
                'bidan' => ['midwife', 'bidan'],
                default => [],
            };

            foreach ($allowedCategoryKeywords as $keyword) {
                if (str_contains($categoryKey, $keyword)) {
                    return true;
                }
            }
        }

        $allowedServiceTypes = match ($profession) {
            'dokter' => ['consultation', 'homecare', 'dokter_homecare', 'konsultasi_tindakan'],
            'perawat' => ['procedure', 'caregiver', 'homecare', 'perawat_homecare', 'konsultasi_tindakan'],
            'bidan' => ['procedure', 'homecare', 'bidan_homecare', 'konsultasi_tindakan'],
            default => [],
        };

        return in_array($service->service_type, $allowedServiceTypes, true);
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

    private function attachDetailBookingActions(ServiceBooking $serviceBooking): void
    {
        $paymentStatus = $serviceBooking->payment?->status;
        $isPaid = $paymentStatus === 'paid';
        $paymentRequiredMessage = $isPaid ? null : 'Pasien harus menyelesaikan pembayaran terlebih dahulu untuk memakai fitur ini.';

        $serviceBooking->setAttribute('detail_actions', [
            'chat' => [
                'label' => 'Chat',
                'enabled' => $isPaid,
                'notifier' => $paymentRequiredMessage,
            ],
            'call' => [
                'label' => 'Call',
                'enabled' => $isPaid,
                'notifier' => $paymentRequiredMessage,
            ],
        ]);
    }
}
