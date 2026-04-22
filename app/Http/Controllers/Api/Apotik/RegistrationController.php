<?php

namespace App\Http\Controllers\Api\Apotik;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\PartnerRegistrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class RegistrationController extends Controller
{
    public function __construct(
        private readonly PartnerRegistrationService $partnerRegistrationService
    ) {
    }

    public function store(Request $request): JsonResponse
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
            'license_number' => ['nullable', 'string', 'max:255', 'unique:pharmacies,license_number'],
            'work_location' => ['nullable', 'string', 'max:500'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'bio' => ['nullable', 'string'],
        ]);

        $pharmacy = $this->partnerRegistrationService->registerPharmacy($user, $validated);

        return response()->json([
            'message' => 'Data apotik berhasil dibuat dan menunggu verifikasi admin.',
            'data' => $pharmacy,
        ], 201);
    }
}
