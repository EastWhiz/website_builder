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

    public static function isActiveOrganizationMember(int $userId, int $organizationId): bool
    {
        if ($userId < 1 || $organizationId < 1) {
            return false;
        }

        return DB::table('organization_user')
            ->where('organization_id', $organizationId)
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->exists();
    }

    /**
     * User IDs that belong to an organization for content scoping (active members + primary owner).
     *
     * @return list<int>
     */
    public static function activeOrganizationMemberUserIds(Organization $organization): array
    {
        $ids = DB::table('organization_user')
            ->where('organization_id', $organization->id)
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $primary = (int) ($organization->primary_user_id ?? 0);
        if ($primary >= 1) {
            $ids[] = $primary;
        }

        return array_values(array_unique($ids));
    }

    /**
     * Point org-scoped user content at a new organization (transfer, activation, etc.).
     *
     * When $fromOrganizationId is a positive id: updates rows for this user where
     * organization_id equals that id OR is NULL (legacy / never stamped).
     * When $fromOrganizationId is null or 0: updates only rows where organization_id IS NULL
     * (first-time assignment to an organization).
     *
     * @return array<string, int> counts keyed by table name
     */
    public static function migrateUserOrganizationScopedData(int $userId, ?int $fromOrganizationId, int $toOrganizationId): array
    {
        if ($userId < 1 || $toOrganizationId < 1) {
            return [];
        }

        $tables = ['angles', 'angle_templates', 'thank_you_pages', 'user_api_instances'];
        $counts = [];

        foreach ($tables as $table) {
            $query = DB::table($table)
                ->where('user_id', $userId)
                ->whereNull('deleted_at');

            if ($fromOrganizationId !== null && $fromOrganizationId > 0) {
                $query->where(function ($q) use ($fromOrganizationId) {
                    $q->where('organization_id', $fromOrganizationId)
                        ->orWhereNull('organization_id');
                });
            } else {
                $query->whereNull('organization_id');
            }

            $counts[$table] = $query->update([
                'organization_id' => $toOrganizationId,
                'updated_at' => now(),
            ]);
        }

        return $counts;
    }
}
