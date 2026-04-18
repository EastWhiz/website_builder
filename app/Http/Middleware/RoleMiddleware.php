<?php

namespace App\Http\Middleware;

use App\Models\Organization;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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

            $user = Auth::user();
            $roleName = $user->role->name ?? null;
            if ($roleName && in_array($roleName, $roles, true)) {
                return $next($request);
            }

            // Organization admins/managers/members often have `users.role_id` pointing at an
            // organization-scoped role row (e.g. name "Org Admin"), not legacy platform names
            // `admin` / `member`. Routes grouped as `role:admin,member` must still allow those users.
            if (in_array('member', $roles, true) && $this->hasActiveOrganizationContext($user)) {
                return $next($request);
            }

            abort(403, 'UNAUTHORIZED ACTION');
        }

        return $next($request);
    }

    private function hasActiveOrganizationContext(\Illuminate\Contracts\Auth\Authenticatable $user): bool
    {
        if (! $user instanceof \App\Models\User) {
            return false;
        }

        if (Organization::query()->where('primary_user_id', $user->id)->exists()) {
            return true;
        }

        return DB::table('organization_user')
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->exists();
    }
}
