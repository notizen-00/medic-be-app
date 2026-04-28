<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class EnsureAdminAccess
{
    /**
     * @param  Closure(Request): mixed  $next
     */
    public function handle(Request $request, Closure $next): mixed
    {
        /** @var User|null $user */
        $user = $request->user();

        if (! $user || $user->role !== 'admin') {
            throw ValidationException::withMessages([
                'user' => ['Hanya akun admin yang dapat mengakses endpoint ini.'],
            ]);
        }

        return $next($request);
    }
}

