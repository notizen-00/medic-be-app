<?php

namespace App\Http\Controllers\Api\Auth;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApotikAuthController extends BaseAuthController
{
    public function login(Request $request): JsonResponse
    {
        return $this->loginMitraByCapability(
            $request,
            'apotik',
            fn (User $user) => $user->pharmacy !== null,
            'Email atau password tidak valid untuk akun mitra apotik.'
        );
    }
}

