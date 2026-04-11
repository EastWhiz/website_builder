<?php

namespace App\Providers;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Compatibility for older MySQL/MariaDB index key limits in tests/live setups.
        Schema::defaultStringLength(191);

        if (env('APP_ENV') == "production") {
            URL::forceScheme('https');
        }
        Vite::prefetch(concurrency: 3);

        Gate::define('org.permission', function (User $user, string $permissionKey): bool {
            if ((int) ($user->role_id ?? 0) === 1 || (($user->role->name ?? null) === 'admin')) {
                return true;
            }

            $organization = $user->currentOrganization();
            if (!$organization) {
                return false;
            }

            $membership = $user->organizations()
                ->where('organizations.id', $organization->id)
                ->wherePivot('status', 'active')
                ->first();

            if (!$membership || empty($membership->pivot->role_id)) {
                return false;
            }

            return \App\Models\RolePermission::query()
                ->where('role_id', (int) $membership->pivot->role_id)
                ->where('permission_key', $permissionKey)
                ->exists();
        });

        Gate::define('org.role.crud', function (User $user): bool {
            // Super Admin or platform admin can fully manage roles.
            return (int) ($user->role_id ?? 0) === 1 || (($user->role->name ?? null) === 'admin');
        });

        /**
         * Who may change their own organization role (team list / edit member).
         * Matches org owner, platform admin, role key org_admin, or seeded org-admin-style permissions.
         */
        Gate::define('org.member.assign_own_org_role', function (User $user, Organization $organization): bool {
            if ((int) ($user->role_id ?? 0) === 1 || (($user->role->name ?? null) === 'admin')) {
                return true;
            }
            if ((int) $organization->primary_user_id === (int) $user->id) {
                return true;
            }
            $key = DB::table('organization_user as ou')
                ->leftJoin('roles as r', 'r.id', '=', 'ou.role_id')
                ->where('ou.organization_id', $organization->id)
                ->where('ou.user_id', $user->id)
                ->whereNull('ou.deleted_at')
                ->where('ou.status', 'active')
                ->value('r.key');
            if ($key === 'org_admin') {
                return true;
            }

            return Gate::forUser($user)->allows('org.permission', 'permission.matrix.update')
                && Gate::forUser($user)->allows('org.permission', 'member.role.assign');
        });

        Gate::define('org.permission.matrix.update', function (User $user): bool {
            if ((int) ($user->role_id ?? 0) === 1 || (($user->role->name ?? null) === 'admin')) {
                return true;
            }

            $organization = $user->currentOrganization();
            if (!$organization) {
                return false;
            }

            $membership = $user->organizations()
                ->where('organizations.id', $organization->id)
                ->wherePivot('status', 'active')
                ->first();

            if (!$membership || empty($membership->pivot->role_id)) {
                return false;
            }

            $hasBasePermission = \App\Models\RolePermission::query()
                ->where('role_id', (int) $membership->pivot->role_id)
                ->where('permission_key', 'permission.matrix.update')
                ->exists();

            if ($hasBasePermission) {
                return true;
            }

            // Delegated manager update flag support (feature-flag style via config/settings can replace this later).
            return (bool) ($user->permission_matrix_update_delegate ?? false);
        });
    }
}
