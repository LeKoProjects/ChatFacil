<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Uso: ->middleware('role:company_admin') ou 'role:platform_admin'.
 */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();
        $allowed = array_map(fn (string $r) => UserRole::from($r), $roles);

        abort_unless($user && in_array($user->role, $allowed, true), 403);

        return $next($request);
    }
}
