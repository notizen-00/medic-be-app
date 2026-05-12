<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Models\ServiceMarkupSetting;
use App\Models\PromoCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ServiceMarkupController extends Controller
{
    /**
     * List semua markup settings
     */
    public function index(Request $request)
    {
        $query = ServiceMarkupSetting::with(['service', 'createdBy']);

        if ($request->has('service_id')) {
            $query->where('service_id', $request->service_id);
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->is_active);
        }

        $settings = $query->latest()->paginate($request->input('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $settings,
        ]);
    }

    /**
     * Detail markup setting
     */
    public function show(ServiceMarkupSetting $serviceMarkupSetting)
    {
        $serviceMarkupSetting->load(['service', 'createdBy', 'updatedBy']);

        return response()->json([
            'success' => true,
            'data' => $serviceMarkupSetting,
        ]);
    }

    /**
     * Create markup setting
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'service_id' => 'required|exists:services,id',
            'markup_type' => 'required|in:percentage,fixed',
            'markup_value' => 'required|numeric|min:0',
            'min_final_price' => 'nullable|numeric|min:0',
            'priority' => 'nullable|integer|min:0',
            'notes' => 'nullable|string|max:1000',
        ]);

        // Cek apakah sudah ada setting untuk service ini
        $existing = ServiceMarkupSetting::where('service_id', $validated['service_id'])
            ->where('is_active', true)
            ->first();

        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => 'Sudah ada markup setting aktif untuk service ini',
            ], 422);
        }

        $validated['created_by'] = Auth::id();
        $validated['is_active'] = true;

        $setting = ServiceMarkupSetting::create($validated);
        $setting->load(['service', 'createdBy']);

        return response()->json([
            'success' => true,
            'message' => 'Markup setting berhasil dibuat',
            'data' => $setting,
        ]);
    }

    /**
     * Update markup setting
     */
    public function update(Request $request, ServiceMarkupSetting $serviceMarkupSetting)
    {
        $validated = $request->validate([
            'markup_type' => 'sometimes|in:percentage,fixed',
            'markup_value' => 'sometimes|numeric|min:0',
            'min_final_price' => 'nullable|numeric|min:0',
            'is_active' => 'sometimes|boolean',
            'priority' => 'nullable|integer|min:0',
            'notes' => 'nullable|string|max:1000',
        ]);

        $validated['updated_by'] = Auth::id();

        $serviceMarkupSetting->update($validated);
        $serviceMarkupSetting->load(['service', 'createdBy', 'updatedBy']);

        return response()->json([
            'success' => true,
            'message' => 'Markup setting berhasil diupdate',
            'data' => $serviceMarkupSetting,
        ]);
    }

    /**
     * Delete markup setting
     */
    public function destroy(ServiceMarkupSetting $serviceMarkupSetting)
    {
        $serviceMarkupSetting->delete();

        return response()->json([
            'success' => true,
            'message' => 'Markup setting berhasil dihapus',
        ]);
    }

    /**
     * Toggle active status markup setting
     */
    public function toggleStatus(Request $request, ServiceMarkupSetting $serviceMarkupSetting)
    {
        $validated = $request->validate([
            'is_active' => 'required|boolean',
        ]);

        $serviceMarkupSetting->update([
            'is_active' => $validated['is_active'],
            'updated_by' => Auth::id(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Status markup setting berhasil diubah',
            'data' => $serviceMarkupSetting,
        ]);
    }
}
