<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function loginPatient(Request $request): JsonResponse
    {
        return $this->loginByRole($request, 'pasien', 'patient');
    }

    public function loginDoctor(Request $request): JsonResponse
    {
        return $this->loginMitraByCapability(
            $request,
            'doctor',
            fn (User $user) => $user->partnerProfile?->profession === 'dokter',
            'Email atau password tidak valid untuk akun dokter.'
        );
    }

    public function loginMitra(Request $request): JsonResponse
    {
        return $this->loginByRole($request, 'mitra', 'mitra');
    }

    public function loginApotik(Request $request): JsonResponse
    {
        return $this->loginMitraByCapability(
            $request,
            'apotik',
            fn (User $user) => $user->pharmacy !== null,
            'Email atau password tidak valid untuk akun mitra apotik.'
        );
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        $user->load(['patientProfile', 'partnerProfile', 'courierProfile', 'pharmacy']);
        $user->setAttribute('profile_user', $this->resolveProfileUser($user));

        return response()->json([
            'message' => 'Data user login berhasil diambil.',
            'data' => $user,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json([
            'message' => 'Logout berhasil.',
        ]);
    }

    private function loginByRole(Request $request, string $role, string $tokenName): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::query()
            ->where('email', $validated['email'])
            ->where('role', $role)
            ->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            return response()->json([
                'message' => 'Email atau password tidak valid untuk akun '.$role.'.',
            ], 422);
        }

        $user->load(['patientProfile', 'partnerProfile', 'courierProfile', 'pharmacy.profile']);

        return response()->json([
            'message' => 'Login berhasil.',
            'data' => $user,
            'user_api_token' => $user->issueApiToken($tokenName.'_api_token'),
        ]);
    }

    private function loginMitraByCapability(
        Request $request,
        string $tokenName,
        \Closure $capability,
        string $invalidMessage
    ): JsonResponse {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::query()
            ->with(['partnerProfile', 'pharmacy.profile'])
            ->where('email', $validated['email'])
            ->where('role', 'mitra')
            ->first();

        if (! $user || ! Hash::check($validated['password'], $user->password) || ! $capability($user)) {
            return response()->json([
                'message' => $invalidMessage,
            ], 422);
        }

        $user->load(['patientProfile', 'partnerProfile', 'courierProfile', 'pharmacy.profile']);

        return response()->json([
            'message' => 'Login berhasil.',
            'data' => $user,
            'user_api_token' => $user->issueApiToken($tokenName.'_api_token'),
        ]);
    }

    private function resolveProfileUser(User $user): mixed
    {
        if ($user->pharmacy) {
            return $user->pharmacy->loadMissing('profile');
        }

        if ($user->partnerProfile) {
            return $user->partnerProfile;
        }

        if ($user->patientProfile) {
            return $user->patientProfile;
        }

        if ($user->courierProfile) {
            return $user->courierProfile;
        }

        return null;
    }
}
