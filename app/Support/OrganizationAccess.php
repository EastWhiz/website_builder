<?php

namespace App\Support;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class OrganizationAccess
{
    public static function isPrivilegedPlatformAdmin(?User $user): bool
    {
        if (!$user) {
            return false;
        }

        return (int) ($user->role_id ?? 0) === 1 || (($user->role->name ?? null) === 'admin');
    }

    public static function canUserFullyManageTeam(User $user, Organization $organization): bool
    {
        if (self::isPrivilegedPlatformAdmin($user)) {
            return true;
        }

        if ((int) $organization->primary_user_id === (int) $user->id) {
            return true;
        }

        $actorMembership = DB::table('organization_user as ou')
            ->leftJoin('roles as r', 'r.id', '=', 'ou.role_id')
            ->where('ou.organization_id', $organization->id)
            ->where('ou.user_id', $user->id)
            ->whereNull('ou.deleted_at')
            ->where('ou.status', 'active')
            ->select('r.key as role_key')
            ->first();

        return ($actorMembership->role_key ?? null) === 'org_admin';
    }
}
