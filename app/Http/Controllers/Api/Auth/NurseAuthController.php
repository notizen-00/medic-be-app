<?php

namespace App\Http\Controllers\Api\Auth;

use App\Models\User;
use App\Services\PartnerRegistrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NurseAuthController extends BaseAuthController
{
    public function __construct(
        private readonly PartnerRegistrationService $partnerRegistrationService
    ) {
    }

    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['required', 'string', 'max:20', 'unique:users,phone'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'specialization' => ['required', 'string', 'max:255'],
            'license_number' => ['required', 'string', 'max:255', 'unique:partner_profiles,license_number'],
            'work_location' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'years_of_experience' => ['nullable', 'integer', 'min:0'],
            'consultation_fee' => ['nullable', 'numeric', 'min:0'],
            'bio' => ['nullable', 'string'],
        ]);

        $nurse = $this->partnerRegistrationService->registerMitraProfessional([
            ...$validated,
            'profession' => 'perawat',
        ]);

        return response()->json([
            'message' => 'Pendaftaran mitra layanan kesehatan berhasil. Akun menunggu verifikasi admin.',
            'data' => $nurse,
            'user_api_token' => $nurse->issueApiToken(),
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        return $this->loginMitraByCapability(
            $request,
            'nurse',
            fn (User $user) => $user->partnerProfile?->profession === 'perawat',
            'Email atau password tidak valid untuk akun perawat.'
        );
    }
}

