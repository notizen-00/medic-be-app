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
        return $this->loginByRole($request, 'dokter', 'doctor');
    }

    public function loginApotik(Request $request): JsonResponse
    {
        return $this->loginByRole($request, 'apotik', 'apotik');
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        $user->load(['patientProfile', 'partnerProfile', 'courierProfile']);

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

        $user->load(['patientProfile', 'partnerProfile', 'courierProfile']);

        return response()->json([
            'message' => 'Login berhasil.',
            'data' => $user,
            'user_api_token' => $user->issueApiToken($tokenName.'_api_token'),
        ]);
    }
}
