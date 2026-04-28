<?php

namespace App\Http\Controllers\Api\Auth;

use App\Models\User;
use App\Services\PartnerRegistrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ApotikRegistrationController extends BaseAuthController
{
    public function __construct(
        private readonly PartnerRegistrationService $partnerRegistrationService
    ) {
    }

    public function register(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user();

        if (! $user || $user->role !== 'mitra') {
            throw ValidationException::withMessages([
                'user' => ['Hanya akun mitra yang dapat membuat apotik.'],
            ]);
        }

        if ($user->pharmacy) {
            throw ValidationException::withMessages([
                'user' => ['Akun mitra ini sudah memiliki data apotik.'],
            ]);
        }

        $validated = $request->validate([
            'pharmacy_name' => ['required', 'string', 'max:255'],
            'license_number' => ['nullable', 'string', 'max:255', 'unique:pharmacy_profiles,license_number'],
            'work_location' => ['nullable', 'string', 'max:500'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'opening_time' => ['nullable', 'date_format:H:i'],
            'closing_time' => ['nullable', 'date_format:H:i', 'after:opening_time'],
            'bio' => ['nullable', 'string'],
        ]);

        $pharmacy = $this->partnerRegistrationService->registerPharmacy($user, $validated);

        return response()->json([
            'message' => 'Data apotik berhasil dibuat dan menunggu verifikasi admin.',
            'data' => $pharmacy,
        ], 201);
    }
}

