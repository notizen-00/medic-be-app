<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ServiceBookingFeeSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ServiceBookingFeeSettingController extends Controller
{
    public function show()
    {
        return response()->json(['success' => true, 'data' => ServiceBookingFeeSetting::activePolicy()]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'transport_distance_threshold_km' => ['required', 'numeric', 'min:0', 'max:1000'],
            'transport_fee_per_visit' => ['required', 'numeric', 'min:0'],
            'hospital_meal_fee_per_visit' => ['required', 'numeric', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $setting = ServiceBookingFeeSetting::query()->latest('id')->first() ?? new ServiceBookingFeeSetting();
        $setting->fill($validated + ['is_active' => true]);
        $setting->updated_by = Auth::id();
        $setting->save();

        return response()->json([
            'success' => true,
            'message' => 'Pengaturan biaya booking berhasil diperbarui.',
            'data' => $setting->fresh('updatedBy'),
        ]);
    }
}
