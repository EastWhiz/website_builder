<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrganizationMemberTransferValidator
{
    /**
     * Validate preconditions for cross-organization member transfer.
     *
     * @return array<string, mixed>
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function validate(int $userId, ?int $sourceOrganizationId, int $targetOrganizationId): array
    {
        $user = User::query()->find($userId);
        if (!$user) {
            throw ValidationException::withMessages([
                'user_id' => 'Selected user was not found.',
            ]);
        }

        $targetOrganization = Organization::query()->find($targetOrganizationId);
        if (!$targetOrganization) {
            throw ValidationException::withMessages([
                'target_organization_id' => 'Target organization was not found.',
            ]);
        }

        $membership = DB::table('organization_user as ou')
            ->leftJoin('roles as r', 'r.id', '=', 'ou.role_id')
            ->where('ou.user_id', $user->id)
            ->whereNull('ou.deleted_at')
            ->select([
                'ou.id as membership_id',
                'ou.organization_id',
                'ou.user_id',
                'ou.role_id',
                'ou.status',
                'r.key as role_key',
                'r.name as role_name',
            ])
            ->first();

        $sourceOrganization = null;
        if ($membership) {
            $sourceOrganization = Organization::query()->find((int) $membership->organization_id);
            if (!$sourceOrganization) {
                throw ValidationException::withMessages([
                    'source_organization_id' => 'User membership points to an invalid source organization.',
                ]);
            }
            if ($sourceOrganizationId !== null && (int) $sourceOrganizationId !== (int) $sourceOrganization->id) {
                throw ValidationException::withMessages([
                    'source_organization_id' => 'Provided source organization does not match user current organization.',
                ]);
            }
        } elseif ($sourceOrganizationId !== null) {
            throw ValidationException::withMessages([
                'source_organization_id' => 'User has no current organization; source organization should be empty.',
            ]);
        }

        if ($sourceOrganization && (int) $sourceOrganization->primary_user_id === (int) $user->id) {
            throw ValidationException::withMessages([
                'user_id' => 'Organization owner/primary user cannot be moved. Reassign owner first, then retry.',
            ]);
        }

        if ($sourceOrganization && (int) $sourceOrganization->id === (int) $targetOrganization->id) {
            throw ValidationException::withMessages([
                'target_organization_id' => 'Selected organization is already assigned to this user.',
            ]);
        }

        $targetMembershipExists = DB::table('organization_user')
            ->where('organization_id', $targetOrganization->id)
            ->where('user_id', $user->id)
            ->when($membership, function ($q) use ($membership) {
                $q->where('id', '!=', (int) $membership->membership_id);
            })
            ->exists();

        if ($targetMembershipExists) {
            throw ValidationException::withMessages([
                'target_organization_id' => 'User already has membership in the target organization.',
            ]);
        }

        $targetRole = null;
        if ($membership && !empty($membership->role_key)) {
            $targetRole = Role::query()
                ->where('scope', 'organization')
                ->where('key', (string) $membership->role_key)
                ->where('is_active', true)
                ->first();
        }
        if (!$targetRole) {
            $targetRole = Role::query()
                ->where('scope', 'organization')
                ->where('key', 'media_buyer')
                ->where('is_active', true)
                ->first();
        }
        if (!$targetRole) {
            $targetRole = Role::query()
                ->where('scope', 'organization')
                ->where('is_active', true)
                ->orderBy('id')
                ->first();
        }

        if (!$targetRole) {
            throw ValidationException::withMessages([
                'user_id' => 'No active organization role is available for assignment.',
            ]);
        }

        return [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'source_organization' => $sourceOrganization ? [
                'id' => $sourceOrganization->id,
                'name' => $sourceOrganization->name,
                'status' => $sourceOrganization->status,
            ] : null,
            'target_organization' => [
                'id' => $targetOrganization->id,
                'name' => $targetOrganization->name,
                'status' => $targetOrganization->status,
            ],
            'membership' => $membership ? [
                'id' => (int) $membership->membership_id,
                'status' => (string) $membership->status,
                'role_id' => (int) ($membership->role_id ?? 0),
                'role_key' => (string) ($membership->role_key ?? ''),
                'role_name' => (string) ($membership->role_name ?? ''),
            ] : null,
            'resolved_target_role' => [
                'id' => $targetRole->id,
                'key' => $targetRole->key,
                'name' => $targetRole->name,
            ],
            'mode' => $membership ? 'move' : 'attach',
        ];
    }
}
