<?php

namespace App\Http\Controllers\Api\Auth;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SessionController extends BaseAuthController
{
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        $user->load(['patientProfile', 'patientMembers', 'partnerProfile', 'courierProfile', 'pharmacy']);
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
}
