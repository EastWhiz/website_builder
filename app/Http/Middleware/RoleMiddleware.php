<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        if (Auth::check()) {
            // Super Admin (user.role_id == 1) bypasses role-name checks.
            if ((int) (Auth::user()->role_id ?? 0) === 1) {
                return $next($request);
            }

            $roleName = Auth::user()->role->name ?? null;
            if (!$roleName || !in_array($roleName, $roles)) {
            // Optionally, you can redirect to a specific page or return a 403 response
            abort(403, 'UNAUTHORIZED ACTION');
            }
        }

        return $next($request);
    }
}
