<?php

namespace App\Http\Controllers\Api\Patient;

use App\Http\Controllers\Controller;
use App\Models\PatientAddress;
use App\Models\PatientMember;
use App\Models\Payment;
use App\Models\Service;
use App\Models\ServiceBooking;
use App\Models\PromoCode;
use App\Services\MidtransService;
use App\Services\ServicePartnerSelectionService;
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
        private readonly ServicePartnerSelectionService $servicePartnerSelectionService
    ) {
    }

    /**
     * List semua service yang tersedia
     */
    public function index(Request $request)
    {
        $query = Service::with('partnerServices')->where('is_active', true);

        if ($request->has('category')) {
            $query->where('category', $request->category);
        }

        if ($request->has('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        $services = $query->latest()->paginate($request->input('per_page', 20));

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
        $service->load('partnerServices');

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
            'patient_member_id' => 'nullable|exists:patient_members,id',
            'patient_address_id' => 'nullable|exists:patient_addresses,id',
            'booking_type' => 'nullable|in:scheduled,daily',
            'scheduled_at' => 'nullable|date|after:now',
            'schedule_start_at' => 'nullable|date|after:now',
            'schedule_end_at' => 'nullable|date|after_or_equal:schedule_start_at',
            'duration_days' => 'nullable|integer|min:1|max:30',
            'notes' => 'nullable|string|max:1000',
            'promo_code' => 'nullable|string',
        ]);

        $user = Auth::user();
        $patientMember = isset($validated['patient_member_id'])
            ? $this->resolvePatientMember((int) $validated['patient_member_id'], $user->id)
            : null;
        $service = Service::query()
            ->with('partnerServices.partner.partnerProfile')
            ->findOrFail($validated['service_id']);
        $basePrice = $this->basePriceFor($service);

        if ($service->is_homecare && ! isset($validated['patient_address_id']) && ! $patientMember?->address) {
            throw ValidationException::withMessages([
                'patient_address_id' => ['Alamat pasien wajib diisi untuk layanan homecare. Kirim patient_address_id atau patient_member_id yang memiliki alamat.'],
            ]);
        }

        $address = $this->resolveBookingAddress($validated, $patientMember);

        $selectedPartnerService = $this->servicePartnerSelectionService
            ->resolveBestPartnerForQuickBooking($service, $address);

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

        $schedule = $this->resolveBookingSchedule($validated);
        $subtotal = $subtotal * $schedule['duration_days'];
        if (($promoCodeData['discount_type'] ?? null) === 'percentage') {
            $discountAmount = $discountAmount * $schedule['duration_days'];
        }
        $finalPrice = $subtotal - $discountAmount;

        $booking = DB::transaction(function () use ($validated, $user, $selectedPartnerService, $schedule, $discountAmount, $promoCodeData, $subtotal, $markupAmount, $finalPrice): ServiceBooking {
            $booking = ServiceBooking::create([
                'booking_code' => 'SVC-' . strtoupper(Str::random(8)),
                'service_id' => $validated['service_id'],
                'patient_user_id' => $user->id,
                'patient_member_id' => $validated['patient_member_id'] ?? null,
                'assigned_partner_user_id' => $selectedPartnerService->partner_user_id,
                'patient_address_id' => $validated['patient_address_id'] ?? null,
                'booking_type' => $schedule['booking_type'],
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
                'markup_amount' => $markupAmount * $schedule['duration_days'],
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

        $booking->load(['service', 'patientMember', 'address', 'assignedPartner.partnerProfile', 'payment']);

        return response()->json([
            'success' => true,
            'message' => 'Service booking berhasil dibuat',
            'data' => [
                'booking' => $booking,
                'pricing' => [
                    'base_price' => $service->price,
                    'markup_amount' => $booking->markup_amount,
                    'subtotal' => $subtotal,
                    'discount_amount' => $discountAmount,
                    'total_amount' => $finalPrice,
                    'duration_days' => $schedule['duration_days'],
                ],
                'matchmaking' => [
                    'partner_service_id' => $selectedPartnerService->id,
                    'partner_user_id' => $selectedPartnerService->partner_user_id,
                    'distance_km' => $selectedPartnerService->distance_km,
                    'match_score' => $selectedPartnerService->match_score,
                    'quality_score' => $selectedPartnerService->quality_score,
                ],
            ],
        ]);
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

        return response()->json([
            'success' => true,
            'data' => $bookings,
        ]);
    }

    private function basePriceFor(Service $service): float
    {
        return (float) ($service->base_price ?? $service->price ?? 0);
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

        return [
            'booking_type' => 'scheduled',
            'scheduled_at' => $scheduledAt,
            'schedule_start_at' => $scheduledAt,
            'schedule_end_at' => $scheduledAt,
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
        if (isset($validated['patient_address_id'])) {
            return PatientAddress::find($validated['patient_address_id']);
        }

        if (! $patientMember || ! $patientMember->address) {
            return null;
        }

        return $patientMember->toPatientAddress();
    }
}
