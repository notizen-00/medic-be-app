<?php

namespace App\Http\Controllers\Api\Patient;

use App\Http\Controllers\Controller;
use App\Events\ServiceBookingMatched;
use App\Models\PatientAddress;
use App\Models\PatientMember;
use App\Models\Payment;
use App\Models\Service;
use App\Models\ServiceBooking;
use App\Models\ServiceBookingHistory;
use App\Services\BalanceService;
use App\Services\AppNotificationService;
use App\Services\MidtransService;
use App\Services\ServicePartnerSelectionService;
use App\Services\ServiceBookingFeeCalculator;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class ServiceBookingController extends Controller
{
    public function __construct(
        private readonly ServicePartnerSelectionService $servicePartnerSelectionService,
        private readonly AppNotificationService $notifications,
        private readonly BalanceService $balanceService,
        private readonly ServiceBookingFeeCalculator $feeCalculator
    ) {
    }

    /**
     * List semua service yang tersedia
     */
    public function index(Request $request)
    {
        $query = Service::with(['serviceCategory', 'partnerServices'])->where('is_active', true);

        if ($request->has('category')) {
            $query->where('category', $request->category);
        }

        if ($request->has('category_id')) {
            $query->where('service_category_id', $request->category_id);
        }

        if ($request->has('service_mode')) {
            $query->where('service_mode', $request->service_mode);
        }

        if ($request->has('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        $services = $query->orderBy('sort_order')->orderBy('name')->paginate($request->input('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $services,
        ]);
    }

    /**
     * Detail service dengan harga setelah markup
     */
    public function show(Service $service)
    {
        $service->load(['serviceCategory', 'partnerServices']);

        // Hitung harga dengan markup
        $pricing = [];
        $markupSetting = \App\Models\ServiceMarkupSetting::getActiveSetting($service->id);

        if ($markupSetting && $markupSetting->is_active) {
            $pricing = [
                'base_price' => $this->basePriceFor($service),
                'markup_type' => $markupSetting->markup_type,
                'markup_value' => $markupSetting->markup_value,
                'markup_amount' => $markupSetting->calculateMarkup($this->basePriceFor($service)),
                'final_price' => $markupSetting->calculateFinalPrice($this->basePriceFor($service)),
            ];
        } else {
            $pricing = [
                'base_price' => $this->basePriceFor($service),
                'markup_amount' => 0,
                'final_price' => $this->basePriceFor($service),
            ];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'service' => $service,
                'pricing' => $pricing,
            ],
        ]);
    }

    /**
     * Cek validitas promo code
     */
    public function checkPromoCode(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string',
            'service_id' => 'required|exists:services,id',
        ]);

        $user = Auth::user();

        $service = Service::find($validated['service_id']);
        $booking = new ServiceBooking();
        $booking->service_id = $validated['service_id'];

        $result = $booking->applyPromoCode($validated['code'], $user->id);

        if ($result['success']) {
            return response()->json([
                'success' => true,
                'message' => 'Promo code valid',
                'data' => $result,
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => $result['message'],
            'data' => $result,
        ], 422);
    }

    /**
     * Create service booking
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'service_id' => 'required|exists:services,id',
            'patient_member_id' => 'required|exists:patient_members,id',
            'patient_address_id' => 'nullable|exists:patient_addresses,id',
            'booking_type' => 'nullable|in:scheduled,daily',
            'visit_plan' => 'nullable|in:once,recurring',
            'recurrence' => 'nullable|required_if:visit_plan,recurring|in:weekly,monthly',
            'visit_count' => 'nullable|integer|min:1|max:52',
            'care_mode' => 'nullable|in:visit,live_in',
            'location_type' => 'nullable|in:home,hospital',
            'scheduled_at' => 'nullable|date|after:now',
            'schedule_start_at' => 'nullable|date|after:now',
            'schedule_end_at' => 'nullable|date|after_or_equal:schedule_start_at',
            'duration_days' => 'nullable|integer|min:1|max:30',
            'notes' => 'nullable|string|max:1000',
            'promo_code' => 'nullable|string',
        ]);

        $user = Auth::user();
        $visitPlan = $validated['visit_plan'] ?? 'once';
        $visitCount = $visitPlan === 'recurring'
            ? (int) ($validated['visit_count'] ?? 0)
            : (($validated['booking_type'] ?? null) === 'daily' ? (int) ($validated['duration_days'] ?? 1) : 1);

        if ($visitPlan === 'recurring' && $visitCount < 2) {
            throw ValidationException::withMessages([
                'visit_count' => ['Booking terjadwal wajib memiliki minimal 2 kunjungan.'],
            ]);
        }

        if ($visitPlan === 'recurring' && ! isset($validated['scheduled_at']) && ! isset($validated['schedule_start_at'])) {
            throw ValidationException::withMessages([
                'scheduled_at' => ['Tanggal kunjungan pertama wajib diisi untuk booking terjadwal.'],
            ]);
        }

        if (($validated['care_mode'] ?? 'visit') === 'live_in' && $visitPlan !== 'recurring') {
            throw ValidationException::withMessages([
                'care_mode' => ['Live-in hanya tersedia untuk booking terjadwal.'],
            ]);
        }
        $patientMember = isset($validated['patient_member_id'])
            ? $this->resolvePatientMember((int) $validated['patient_member_id'], $user->id)
            : null;
        $service = Service::query()
            ->with('partnerServices.partner.partnerProfile')
            ->findOrFail($validated['service_id']);
        $basePrice = $this->basePriceFor($service);

        if (! $service->is_active) {
            throw ValidationException::withMessages([
                'service_id' => ['Layanan yang dipilih sedang tidak aktif.'],
            ]);
        }

        if ($this->serviceRequiresAddress($service) && ! $patientMember?->address) {
            throw ValidationException::withMessages([
                'patient_member_id' => ['Profil pasien wajib memiliki alamat untuk layanan ini.'],
            ]);
        }

        if ($service->requires_schedule && ! isset($validated['scheduled_at']) && ! isset($validated['schedule_start_at'])) {
            throw ValidationException::withMessages([
                'scheduled_at' => ['Jadwal wajib diisi untuk layanan ini.'],
            ]);
        }

        $address = $this->resolveBookingAddress($validated, $patientMember);
        $selectedPartnerService = $this->servicePartnerSelectionService
            ->resolveNearestPartnerForBooking($service, $address);
        $schedule = $this->resolveBookingSchedule($validated);
        $fees = $this->feeCalculator->calculate([
            'visit_plan' => $visitPlan,
            'visit_count' => $visitCount,
            'care_mode' => $validated['care_mode'] ?? 'visit',
            'location_type' => $validated['location_type'] ?? 'home',
            'distance_km' => $selectedPartnerService->distance_km,
        ]);

        // Get markup setting
        $markupSetting = \App\Models\ServiceMarkupSetting::getActiveSetting($service->id);
        $markupAmount = 0;

        if ($markupSetting && $markupSetting->is_active) {
            $markupAmount = $markupSetting->calculateMarkup($basePrice);
        }

        // Subtotal = base price + markup
        $subtotal = $basePrice + $markupAmount;

        // Handle promo code
        $discountAmount = 0;
        $promoCodeData = null;

        if (isset($validated['promo_code']) && $validated['promo_code']) {
            $promoBooking = new ServiceBooking();
            $promoBooking->service_id = $validated['service_id'];
            $result = $promoBooking->applyPromoCode($validated['promo_code'], $user->id);

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                ], 422);
            }

            $discountAmount = $result['discount_amount'];
            $promoCodeData = $result;
        }

        $subtotal = $subtotal * $visitCount;
        if (($promoCodeData['discount_type'] ?? null) === 'percentage') {
            $discountAmount = $discountAmount * $visitCount;
        }
        $finalPrice = $subtotal - $discountAmount + $fees['transport_fee'] + $fees['meal_fee'];

        $booking = DB::transaction(function () use ($validated, $user, $schedule, $visitPlan, $visitCount, $fees, $discountAmount, $promoCodeData, $subtotal, $markupAmount, $finalPrice, $selectedPartnerService): ServiceBooking {
            $booking = ServiceBooking::create([
                'booking_code' => 'SVC-' . strtoupper(Str::random(8)),
                'service_id' => $validated['service_id'],
                'patient_user_id' => $user->id,
                'patient_member_id' => $validated['patient_member_id'] ?? null,
                'assigned_partner_user_id' => $selectedPartnerService->partner_user_id,
                'patient_address_id' => null,
                'booking_type' => $schedule['booking_type'],
                'visit_plan' => $visitPlan,
                'recurrence' => $visitPlan === 'recurring' ? $validated['recurrence'] : null,
                'visit_count' => $visitCount,
                'care_mode' => $validated['care_mode'] ?? 'visit',
                'location_type' => $validated['location_type'] ?? 'home',
                'distance_km' => $selectedPartnerService->distance_km,
                'scheduled_at' => $schedule['scheduled_at'],
                'schedule_start_at' => $schedule['schedule_start_at'],
                'schedule_end_at' => $schedule['schedule_end_at'],
                'duration_days' => $schedule['duration_days'],
                'notes' => $validated['notes'] ?? null,
                'status' => 'pending',
                'promo_code' => $validated['promo_code'] ?? null,
                'discount_amount' => $discountAmount,
                'discount_type' => $promoCodeData['discount_type'] ?? null,
                'subtotal' => $subtotal,
                'markup_amount' => $markupAmount * $visitCount,
                'transport_fee' => $fees['transport_fee'],
                'meal_fee' => $fees['meal_fee'],
                'fee_policy_snapshot' => $fees['policy_snapshot'],
                'total_amount' => $finalPrice,
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

        $booking->load(['service', 'patient', 'patientMember', 'address', 'assignedPartner.partnerProfile', 'payment']);
        $booking->useServiceAddressRelation();

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
            'body' => 'Ada pesanan layanan baru yang cocok untuk Anda. Terima pesanan, lalu tunggu pasien menyelesaikan pembayaran.',
            'action_url' => '/mitra/service-bookings/'.$booking->id,
            'reference_type' => 'service_booking',
            'reference_id' => $booking->id,
            'data' => [
                'service_booking_id' => $booking->id,
                'booking_code' => $booking->booking_code,
                'patient_user_id' => $booking->patient_user_id,
                'assigned_partner_user_id' => $booking->assigned_partner_user_id,
                'status' => $booking->status,
                'payment_status' => $booking->payment?->status,
                'matchmaking' => $matchmaking,
            ],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Service booking berhasil dibuat dan dikirim ke mitra. Lanjutkan pembayaran setelah mitra menerima pesanan.',
            'data' => [
                'booking' => $booking,
                'pricing' => [
                    'base_price' => $basePrice,
                    'markup_amount' => $booking->markup_amount,
                    'subtotal' => $subtotal,
                    'discount_amount' => $discountAmount,
                    'transport_fee' => $booking->transport_fee,
                    'meal_fee' => $booking->meal_fee,
                    'total_amount' => $finalPrice,
                    'visit_count' => $visitCount,
                ],
                'matchmaking' => $matchmaking,
                'matchmaking_status' => 'waiting_partner_acceptance',
            ],
        ], 201);
    }

    /**
     * Show booking detail
     */
    public function showBooking(ServiceBooking $serviceBooking)
    {
        // $this->authorize('view', $serviceBooking);

        if ($serviceBooking->patient_user_id !== Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses ke booking ini',
            ], 403);
        }

        $serviceBooking->load(['service', 'patientMember', 'address', 'assignedPartner.partnerProfile', 'histories.actor', 'partnerBalanceTransaction', 'payment']);
        $serviceBooking->useServiceAddressRelation();

        return response()->json([
            'success' => true,
            'data' => $serviceBooking,
        ]);
    }

    /**
     * List booking user
     */
    public function indexBookings(Request $request)
    {
        $user = Auth::user();

        $query = ServiceBooking::where('patient_user_id', $user->id)
            ->with(['service', 'patientMember', 'address', 'assignedPartner.partnerProfile', 'histories.actor', 'payment']);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $bookings = $query->latest()->paginate($request->input('per_page', 20));
        $bookings->getCollection()->each->useServiceAddressRelation();

        return response()->json([
            'success' => true,
            'data' => $bookings,
        ]);
    }

    private function basePriceFor(Service $service): float
    {
        return (float) ($service->base_price ?? $service->price ?? 0);
    }

    private function serviceRequiresAddress(Service $service): bool
    {
        return (bool) ($service->requires_address ?? $service->is_homecare);
    }

    public function pay(Request $request, ServiceBooking $serviceBooking, MidtransService $midtransService)
    {
        if ($serviceBooking->patient_user_id !== Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses untuk membayar booking ini',
            ], 403);
        }

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
            $serviceBooking->load(['service', 'patientMember', 'address', 'assignedPartner.partnerProfile', 'payment']);
            $serviceBooking->useServiceAddressRelation();

            return response()->json([
                'success' => true,
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
            Log::error('Gagal membuat Snap token Midtrans untuk service booking patient.', [
                'service_booking_id' => $serviceBooking->id,
                'payment_id' => $payment->id,
                'payment_code' => $payment->payment_code,
                'error' => $exception->getMessage(),
            ]);

            throw ValidationException::withMessages([
                'payment' => [$exception->getMessage()],
            ]);
        }

        $serviceBooking->load(['service', 'patientMember', 'address', 'assignedPartner.partnerProfile', 'payment']);
        $serviceBooking->useServiceAddressRelation();

        return response()->json([
            'success' => true,
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

    public function confirmCompletion(Request $request, ServiceBooking $serviceBooking)
    {
        if ($serviceBooking->patient_user_id !== Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses untuk mengonfirmasi booking ini',
            ], 403);
        }

        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $serviceBooking = DB::transaction(function () use ($serviceBooking, $validated): ServiceBooking {
            $lockedBooking = ServiceBooking::query()
                ->with(['payment', 'assignedPartner'])
                ->lockForUpdate()
                ->findOrFail($serviceBooking->id);

            if (! $lockedBooking->assigned_partner_user_id || ! $lockedBooking->assignedPartner) {
                throw ValidationException::withMessages([
                    'service_booking' => ['Booking belum memiliki mitra yang ditugaskan.'],
                ]);
            }

            if (! in_array($lockedBooking->status, ['confirmed', 'scheduled', 'on_the_way', 'completed'], true)) {
                throw ValidationException::withMessages([
                    'status' => ['Booking hanya dapat dikonfirmasi selesai setelah diterima atau diproses mitra.'],
                ]);
            }

            if (! $lockedBooking->payment || $lockedBooking->payment->status !== 'paid') {
                throw ValidationException::withMessages([
                    'payment' => ['Booking hanya dapat dikonfirmasi selesai setelah pembayaran lunas.'],
                ]);
            }

            $lockedBooking->update([
                'status' => 'completed',
                'completed_at' => $lockedBooking->completed_at ?? now(),
                'notes' => $validated['notes'] ?? $lockedBooking->notes,
            ]);

            ServiceBookingHistory::create([
                'service_booking_id' => $lockedBooking->id,
                'actor_user_id' => Auth::id(),
                'type' => 'status',
                'title' => 'Pasien mengonfirmasi layanan selesai',
                'description' => $validated['notes'] ?? 'Pasien mengonfirmasi layanan telah selesai.',
                'meta' => ['status' => 'completed', 'patient_confirmed' => true],
                'handled_at' => now(),
            ]);

            if ((float) $lockedBooking->total_amount > 0 && $lockedBooking->partner_balance_transaction_id === null) {
                $balance = $this->balanceService->getOrCreateBalance($lockedBooking->assignedPartner);
                $transaction = $this->balanceService->credit($balance, (float) $lockedBooking->total_amount, [
                    'reference_type' => 'service_booking',
                    'reference_id' => $lockedBooking->id,
                    'booking_code' => $lockedBooking->booking_code,
                    'description' => 'Pendapatan layanan '.$lockedBooking->booking_code,
                    'confirmed_by_patient' => true,
                ]);

                $lockedBooking->update([
                    'partner_paid_at' => now(),
                    'partner_balance_transaction_id' => $transaction->id,
                ]);
            }

            return $lockedBooking;
        });

        $serviceBooking->load(['service', 'patientMember', 'address', 'assignedPartner.partnerProfile', 'histories.actor', 'partnerBalanceTransaction', 'payment']);
        $serviceBooking->useServiceAddressRelation();

        if ($serviceBooking->assigned_partner_user_id) {
            $this->notifications->send($serviceBooking->assigned_partner_user_id, [
                'type' => 'service_booking.patient_confirmed_completed',
                'title' => 'Pasien mengonfirmasi layanan selesai',
                'body' => 'Pasien mengonfirmasi pesanan '.$serviceBooking->booking_code.' selesai. Saldo mitra sudah diperbarui.',
                'action_url' => '/mitra/service-bookings/'.$serviceBooking->id,
                'reference_type' => 'service_booking',
                'reference_id' => $serviceBooking->id,
                'data' => [
                    'service_booking_id' => $serviceBooking->id,
                    'booking_code' => $serviceBooking->booking_code,
                    'status' => $serviceBooking->status,
                    'partner_balance_transaction_id' => $serviceBooking->partner_balance_transaction_id,
                ],
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Konfirmasi selesai berhasil. Saldo mitra sudah diperbarui.',
            'data' => $serviceBooking,
        ]);
    }

    public function tracking(ServiceBooking $serviceBooking)
    {
        if ($serviceBooking->patient_user_id !== Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses ke tracking booking ini',
            ], 403);
        }

        $serviceBooking->load(['assignedPartner.partnerProfile', 'patientMember', 'address', 'partnerLocation']);
        $serviceBooking->useServiceAddressRelation();

        $address = $serviceBooking->serviceAddress();
        $partnerLocation = $serviceBooking->partnerLocation;

        return response()->json([
            'success' => true,
            'data' => [
                'service_booking_id' => $serviceBooking->id,
                'booking_code' => $serviceBooking->booking_code,
                'status' => $serviceBooking->status,
                'assigned_partner_user_id' => $serviceBooking->assigned_partner_user_id,
                'partner' => $serviceBooking->assignedPartner ? [
                    'id' => $serviceBooking->assignedPartner->id,
                    'name' => $serviceBooking->assignedPartner->name,
                    'phone' => $serviceBooking->assignedPartner->phone,
                    'partner_profile' => $serviceBooking->assignedPartner->partnerProfile,
                ] : null,
                'partner_location' => [
                    'latitude' => $partnerLocation?->latitude,
                    'longitude' => $partnerLocation?->longitude,
                    'accuracy_meters' => $partnerLocation?->accuracy_meters,
                    'heading' => $partnerLocation?->heading,
                    'speed_mps' => $partnerLocation?->speed_mps,
                    'updated_at' => $partnerLocation?->recorded_at?->toISOString(),
                ],
                'destination' => $address ? [
                    'id' => $address->id,
                    'label' => $address->label,
                    'recipient_name' => $address->recipient_name,
                    'recipient_phone' => $address->recipient_phone,
                    'address' => $address->address,
                    'latitude' => $address->latitude,
                    'longitude' => $address->longitude,
                ] : null,
                'channel' => 'private-service-booking.'.$serviceBooking->id.'.tracking',
                'event' => 'service-booking.location.updated',
            ],
        ]);
    }

    private function resolveBookingSchedule(array $validated): array
    {
        $bookingType = $validated['booking_type'] ?? 'scheduled';
        $scheduledAt = isset($validated['scheduled_at']) ? Carbon::parse($validated['scheduled_at']) : null;

        if ($bookingType === 'daily') {
            $startAt = isset($validated['schedule_start_at'])
                ? Carbon::parse($validated['schedule_start_at'])
                : $scheduledAt;

            if (! $startAt) {
                throw ValidationException::withMessages([
                    'schedule_start_at' => ['Tanggal mulai wajib diisi untuk booking harian.'],
                ]);
            }

            $durationDays = (int) ($validated['duration_days'] ?? 1);
            $endAt = isset($validated['schedule_end_at'])
                ? Carbon::parse($validated['schedule_end_at'])
                : $startAt->copy()->addDays($durationDays - 1);

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

        $visitPlan = $validated['visit_plan'] ?? 'once';
        $visitCount = $visitPlan === 'recurring' ? (int) ($validated['visit_count'] ?? 1) : 1;
        $endAt = $scheduledAt?->copy();

        if ($endAt && $visitPlan === 'recurring') {
            $endAt = ($validated['recurrence'] ?? null) === 'monthly'
                ? $endAt->addMonthsNoOverflow($visitCount - 1)
                : $endAt->addWeeks($visitCount - 1);
        }

        return [
            'booking_type' => 'scheduled',
            'scheduled_at' => $scheduledAt,
            'schedule_start_at' => $scheduledAt,
            'schedule_end_at' => $endAt,
            'duration_days' => 1,
        ];
    }

    private function resolvePatientMember(int $patientMemberId, int $ownerUserId): PatientMember
    {
        return PatientMember::query()
            ->where('owner_user_id', $ownerUserId)
            ->findOrFail($patientMemberId);
    }

    private function resolveBookingAddress(array $validated, ?PatientMember $patientMember): ?PatientAddress
    {
        if ($patientMember?->address) {
            return $patientMember->toPatientAddress();
        }

        return isset($validated['patient_address_id'])
            ? PatientAddress::find($validated['patient_address_id'])
            : null;
    }
}
