<?php

namespace App\Http\Controllers\Api\Auth;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminAuthController extends BaseAuthController
{
    public function login(Request $request): JsonResponse
    {
        return $this->loginByRole($request, 'admin', 'admin');
    }
}

