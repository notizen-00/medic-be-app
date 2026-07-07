<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class BaseAuthController extends Controller
{
    protected function loginByRole(Request $request, string $role, string $tokenName): JsonResponse
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

        $user->load(['patientProfile', 'patientMembers', 'partnerProfile', 'courierProfile', 'pharmacy.profile']);

        return response()->json([
            'message' => 'Login berhasil.',
            'data' => $user,
            'user_api_token' => $user->issueApiToken($tokenName.'_api_token'),
        ]);
    }

    protected function loginMitraByCapability(
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

        $user->load(['patientProfile', 'patientMembers', 'partnerProfile', 'courierProfile', 'pharmacy.profile']);

        return response()->json([
            'message' => 'Login berhasil.',
            'data' => $user,
            'user_api_token' => $user->issueApiToken($tokenName.'_api_token'),
        ]);
    }

    protected function resolveProfileUser(User $user): mixed
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
