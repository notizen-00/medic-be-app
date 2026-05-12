<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\PromoCode;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PromoCodeController extends Controller
{
    /**
     * List semua promo codes
     */
    public function index(Request $request)
    {
        $query = PromoCode::with(['service', 'createdBy']);

        if ($request->has('is_active')) {
            $query->where('is_active', $request->is_active);
        }

        if ($request->has('service_id')) {
            $query->where('service_id', $request->service_id);
        }

        if ($request->has('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('code', 'like', '%' . $request->search . '%')
                    ->orWhere('name', 'like', '%' . $request->search . '%');
            });
        }

        $promoCodes = $query->latest()->paginate($request->input('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $promoCodes,
        ]);
    }

    /**
     * Detail promo code
     */
    public function show(PromoCode $promoCode)
    {
        $promoCode->load(['service', 'createdBy']);

        // Hitung usage stats
        $promoCode->usage_stats = [
            'total_uses' => $promoCode->usesCount(),
            'max_uses' => $promoCode->max_uses,
            'remaining_uses' => $promoCode->max_uses ? ($promoCode->max_uses - $promoCode->usesCount()) : null,
        ];

        return response()->json([
            'success' => true,
            'data' => $promoCode,
        ]);
    }

    /**
     * Create promo code
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|unique:promo_codes,code',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'discount_type' => 'required|in:percentage,fixed',
            'discount_value' => 'required|numeric|min:0',
            'min_purchase' => 'nullable|numeric|min:0',
            'max_discount' => 'nullable|numeric|min:0',
            'max_uses' => 'nullable|integer|min:1',
            'max_uses_per_user' => 'nullable|integer|min:1',
            'valid_from' => 'nullable|date',
            'valid_until' => 'nullable|date|after_or_equal:valid_from',
            'service_id' => 'nullable|exists:services,id',
        ]);

        $validated['is_active'] = true;
        $validated['created_by'] = Auth::id();

        $promoCode = PromoCode::create($validated);
        $promoCode->load(['service', 'createdBy']);

        return response()->json([
            'success' => true,
            'message' => 'Promo code berhasil dibuat',
            'data' => $promoCode,
        ]);
    }

    /**
     * Update promo code
     */
    public function update(Request $request, PromoCode $promoCode)
    {
        $validated = $request->validate([
            'code' => 'sometimes|string|unique:promo_codes,code,' . $promoCode->id,
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string|max:1000',
            'discount_type' => 'sometimes|in:percentage,fixed',
            'discount_value' => 'sometimes|numeric|min:0',
            'min_purchase' => 'nullable|numeric|min:0',
            'max_discount' => 'nullable|numeric|min:0',
            'max_uses' => 'nullable|integer|min:1',
            'max_uses_per_user' => 'nullable|integer|min:1',
            'valid_from' => 'nullable|date',
            'valid_until' => 'nullable|date',
            'service_id' => 'nullable|exists:services,id',
            'is_active' => 'sometimes|boolean',
        ]);

        $promoCode->update($validated);
        $promoCode->load(['service', 'createdBy']);

        return response()->json([
            'success' => true,
            'message' => 'Promo code berhasil diupdate',
            'data' => $promoCode,
        ]);
    }

    /**
     * Delete promo code
     */
    public function destroy(PromoCode $promoCode)
    {
        $promoCode->delete();

        return response()->json([
            'success' => true,
            'message' => 'Promo code berhasil dihapus',
        ]);
    }

    /**
     * Toggle active status promo code
     */
    public function toggleStatus(Request $request, PromoCode $promoCode)
    {
        $validated = $request->validate([
            'is_active' => 'required|boolean',
        ]);

        $promoCode->update([
            'is_active' => $validated['is_active'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Status promo code berhasil diubah',
            'data' => $promoCode,
        ]);
    }

    /**
     * List promo codes yang tersedia untuk user
     */
    public function availableCodes(Request $request)
    {
        $userId = $request->user()->id;

        $query = PromoCode::where('is_active', true)
            ->with(['service'])
            ->where(function ($q) {
                $q->whereNull('valid_from')
                    ->orWhere('valid_from', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('valid_until')
                    ->orWhere('valid_until', '>=', now());
            })
            ->where(function ($q) use ($userId) {
                $q->whereNull('max_uses')
                    ->orWhereRaw('(SELECT COUNT(*) FROM service_bookings WHERE promo_code = promo_codes.code AND status != "cancelled") < max_uses');
            })
            ->where(function ($q) use ($userId) {
                $q->where('max_uses_per_user', '>=', 2)
                    ->orWhereRaw('(SELECT COUNT(*) FROM service_bookings WHERE promo_code = promo_codes.code AND patient_user_id = ? AND status != "cancelled") < max_uses_per_user', [$userId]);
            });

        $promoCodes = $query->get();

        return response()->json([
            'success' => true,
            'data' => $promoCodes,
        ]);
    }
}
