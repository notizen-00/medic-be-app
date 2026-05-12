<?php

namespace App\Http\Controllers\Api\Patient;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Models\ServiceBooking;
use App\Models\PromoCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class ServiceBookingController extends Controller
{
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
                'base_price' => $service->price,
                'markup_type' => $markupSetting->markup_type,
                'markup_value' => $markupSetting->markup_value,
                'markup_amount' => $markupSetting->calculateMarkup($service->price),
                'final_price' => $markupSetting->calculateFinalPrice($service->price),
            ];
        } else {
            $pricing = [
                'base_price' => $service->price,
                'markup_amount' => 0,
                'final_price' => $service->price,
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
            'patient_address_id' => 'nullable|exists:patient_addresses,id',
            'scheduled_at' => 'nullable|date|after:now',
            'notes' => 'nullable|string|max:1000',
            'promo_code' => 'nullable|string',
        ]);

        $user = Auth::user();
        $service = Service::find($validated['service_id']);

        // Get markup setting
        $markupSetting = \App\Models\ServiceMarkupSetting::getActiveSetting($service->id);
        $markupAmount = 0;

        if ($markupSetting && $markupSetting->is_active) {
            $markupAmount = $markupSetting->calculateMarkup($service->price);
        }

        // Subtotal = base price + markup
        $subtotal = $service->price + $markupAmount;

        // Handle promo code
        $discountAmount = 0;
        $promoCodeData = null;

        if (isset($validated['promo_code']) && $validated['promo_code']) {
            $result = (new ServiceBooking())->applyPromoCode($validated['promo_code'], $user->id);

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                ], 422);
            }

            $discountAmount = $result['discount_amount'];
            $promoCodeData = $result;
        }

        // Final price
        $finalPrice = $subtotal - $discountAmount;

        // Create booking
        $booking = ServiceBooking::create([
            'booking_code' => 'SVC-' . strtoupper(Str::random(8)),
            'service_id' => $validated['service_id'],
            'patient_user_id' => $user->id,
            'patient_address_id' => $validated['patient_address_id'] ?? null,
            'scheduled_at' => $validated['scheduled_at'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'status' => 'pending',
            'promo_code' => $validated['promo_code'] ?? null,
            'discount_amount' => $discountAmount,
            'discount_type' => $promoCodeData['discount_type'] ?? null,
            'subtotal' => $subtotal,
            'markup_amount' => $markupAmount,
            'total_amount' => $finalPrice,
        ]);

        $booking->load(['service', 'address']);

        return response()->json([
            'success' => true,
            'message' => 'Service booking berhasil dibuat',
            'data' => [
                'booking' => $booking,
                'pricing' => [
                    'base_price' => $service->price,
                    'markup_amount' => $markupAmount,
                    'subtotal' => $subtotal,
                    'discount_amount' => $discountAmount,
                    'total_amount' => $finalPrice,
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

        $serviceBooking->load(['service', 'address', 'assignedPartner']);

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
            ->with(['service', 'address', 'assignedPartner']);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $bookings = $query->latest()->paginate($request->input('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $bookings,
        ]);
    }
}
