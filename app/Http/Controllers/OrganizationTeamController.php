<?php

namespace App\Http\Controllers;

use App\Models\Angle;
use App\Models\AngleTemplate;
use App\Models\Organization;
use App\Models\OrganizationMailSetting;
use App\Models\OtpServiceCredential;
use App\Models\Role;
use App\Models\User;
use App\Models\UserApiCredential;
use App\Models\UserApiInstance;
use App\Notifications\OrganizationTeamInvitationNotification;
use App\Services\OrganizationMailerRegistry;
use App\Support\OrganizationAccess;
use Illuminate\Auth\Events\PasswordResetLinkSent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OrganizationTeamController extends Controller
{
    private function denyUnlessCan(Request $request, string $permissionKey)
    {
        if (!Gate::forUser($request->user())->allows('org.permission', $permissionKey)) {
            return sendResponse(false, 'Unauthorized action.', null, null, null, 403);
        }

        return null;
    }

    /**
     * Whether the membership row refers to an organization-scoped org_admin role.
     * Pass rows from organization_user (role_id) or joined rows that include role_key.
     */
    private function membershipRepresentsOrgAdmin(object $row): bool
    {
        if (($row->role_key ?? null) === 'org_admin') {
            return true;
        }
        $roleId = (int) ($row->role_id ?? $row->organization_role_id ?? 0);
        if ($roleId <= 0) {
            return false;
        }

        return Role::query()
            ->where('id', $roleId)
            ->where('scope', 'organization')
            ->where('key', 'org_admin')
            ->exists();
    }

    /**
     * Org admin accounts may be viewed or changed only by org owner / org_admin key / platform admin.
     *
     * @return \Illuminate\Http\JsonResponse|null
     */
    private function denyUnlessCanManageOrgAdminTarget(Request $request, Organization $organization, object $membershipRow): ?\Illuminate\Http\JsonResponse
    {
        if (!$this->membershipRepresentsOrgAdmin($membershipRow)) {
            return null;
        }
        $user = $request->user();
        if ($user && OrganizationAccess::canUserFullyManageTeam($user, $organization)) {
            return null;
        }

        return sendResponse(
            false,
            'Only an organization administrator can view or modify organization admin accounts.',
            null,
            null,
            null,
            403
        );
    }

    /**
     * Org role changes for another member are gated by member.role.assign / member.edit.
     * Changing your own org role is allowed only for org owner, org_admin key, or platform admin.
     */
    private function mayActorChangeTargetOrgRole(Request $request, Organization $organization, int $targetUserId): bool
    {
        $user = $request->user();
        if (!$user) {
            return false;
        }
        if ((int) $targetUserId !== (int) $user->id) {
            return true;
        }

        return Gate::forUser($user)->allows('org.member.assign_own_org_role', $organization);
    }

    /**
     * Load/update full member profile: organization owner, or anyone with member.edit (incl. platform admin via org.permission).
     */
    private function canViewOrEditTeamMemberDetails(Request $request, Organization $organization): bool
    {
        $user = $request->user();
        if (!$user) {
            return false;
        }
        if ((int) $organization->primary_user_id === (int) $user->id) {
            return true;
        }

        return Gate::forUser($user)->allows('org.permission', 'member.edit');
    }

    private function inviterOrganizationRoleLabel(Request $request, Organization $organization): string
    {
        $user = $request->user();
        if (!$user) {
            return '';
        }
        if (OrganizationAccess::isPrivilegedPlatformAdmin($user)) {
            return 'Platform Administrator';
        }
        $roleName = DB::table('organization_user as ou')
            ->leftJoin('roles as r', 'r.id', '=', 'ou.role_id')
            ->where('ou.organization_id', $organization->id)
            ->where('ou.user_id', $user->id)
            ->whereNull('ou.deleted_at')
            ->where('ou.status', 'active')
            ->value('r.name');

        return $roleName ? (string) $roleName : 'Team member';
    }

    private function resolveOrganization(Request $request): ?Organization
    {
        $user = $request->user();
        $organization = $user?->currentOrganization();

        // Allow privileged platform admins to target organization explicitly.
        if (!$organization && OrganizationAccess::isPrivilegedPlatformAdmin($user) && $request->filled('organization_id')) {
            $organization = Organization::find((int) $request->get('organization_id'));
        }

        return $organization;
    }

    /**
     * Step 3.2: Team members listing API with active/archived toggle.
     */
    public function membersIndex(Request $request)
    {
        $organization = $this->resolveOrganization($request);

        if (!$organization) {
            return sendResponse(false, 'No active organization context found.', null, null, null, 422);
        }

        $pageCount = (int) $request->get('page_count', 10);
        if ($pageCount <= 0) {
            $pageCount = 10;
        }
        if ($pageCount > 100) {
            $pageCount = 100;
        }

        $query = DB::table('organization_user as ou')
            ->join('users as u', 'u.id', '=', 'ou.user_id')
            ->leftJoin('roles as r', 'r.id', '=', 'ou.role_id')
            ->where('ou.organization_id', $organization->id)
            ->select([
                'ou.id as membership_id',
                'ou.organization_id',
                'ou.user_id',
                'ou.role_id as organization_role_id',
                'ou.status as membership_status',
                'ou.invited_at',
                'ou.accepted_at',
                'ou.deleted_at as membership_deleted_at',
                'u.name',
                'u.email',
                'u.phone',
                'u.deleted_at as user_deleted_at',
                'r.name as role_name',
                'r.key as role_key',
            ]);

        $archived = strtolower((string) $request->get('archived', 'false'));
        $showArchived = in_array($archived, ['1', 'true', 'yes'], true);
        if ($showArchived) {
            $query->where(function ($q) {
                $q->whereNotNull('ou.deleted_at')->orWhereNotNull('u.deleted_at');
            });
        } else {
            $query->whereNull('ou.deleted_at')->whereNull('u.deleted_at');
        }

        $search = trim((string) $request->get('q', ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('u.name', 'LIKE', '%' . $search . '%')
                    ->orWhere('u.email', 'LIKE', '%' . $search . '%')
                    ->orWhere('u.phone', 'LIKE', '%' . $search . '%')
                    ->orWhere('r.name', 'LIKE', '%' . $search . '%');
            });
        }

        $sort = trim((string) $request->get('sort', 'ou.id desc'));
        [$sortCol, $sortDir] = array_pad(explode(' ', $sort), 2, 'desc');
        $sortDir = strtolower($sortDir) === 'asc' ? 'asc' : 'desc';
        $allowedSortCols = ['ou.id', 'u.name', 'u.email', 'ou.created_at', 'ou.status'];
        if (!in_array($sortCol, $allowedSortCols, true)) {
            $sortCol = 'ou.id';
        }
        $query->orderBy($sortCol, $sortDir);

        $members = $query->cursorPaginate($pageCount);

        return sendResponse(true, 'Team members retrieved successfully!', [
            'organization' => [
                'id' => $organization->id,
                'name' => $organization->name,
                'status' => $organization->status,
                'organization_mail_configured' => OrganizationMailSetting::query()
                    ->where('organization_id', $organization->id)
                    ->exists(),
            ],
            'members' => $members,
        ]);
    }

    public function rolesIndex(Request $request)
    {
        $gate = Gate::forUser($request->user());
        $canList = $gate->allows('org.permission', 'role.view')
            || $gate->allows('org.permission', 'member.invite')
            || $gate->allows('org.permission', 'member.role.assign')
            || $gate->allows('org.permission', 'member.edit');
        if (!$canList) {
            return sendResponse(false, 'Unauthorized action.', null, null, null, 403);
        }

        $roles = Role::query()
            ->where('scope', 'organization')
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'key']);

        if ($roles->isEmpty()) {
            $roles = Role::query()
                ->where('scope', 'organization')
                ->where('is_system', true)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'key']);
        }

        return sendResponse(true, 'Organization roles retrieved successfully!', $roles);
    }

    /**
     * Get single member details for edit page.
     */
    public function showMember(Request $request, int $membershipId)
    {
        $organization = $this->resolveOrganization($request);
        if (!$organization) {
            return sendResponse(false, 'No active organization context found.', null, null, null, 422);
        }
        if (!$this->canViewOrEditTeamMemberDetails($request, $organization)) {
            return sendResponse(false, 'You are not allowed to view this team member.', null, null, null, 403);
        }

        $member = DB::table('organization_user as ou')
            ->join('users as u', 'u.id', '=', 'ou.user_id')
            ->leftJoin('roles as r', 'r.id', '=', 'ou.role_id')
            ->where('ou.organization_id', $organization->id)
            ->where('ou.id', $membershipId)
            ->select([
                'ou.id as membership_id',
                'ou.organization_id',
                'ou.user_id',
                'ou.role_id as organization_role_id',
                'ou.status as membership_status',
                'ou.invited_at',
                'ou.accepted_at',
                'ou.deleted_at as membership_deleted_at',
                'u.name',
                'u.email',
                'u.phone',
                'u.deleted_at as user_deleted_at',
                'r.name as role_name',
                'r.key as role_key',
            ])
            ->first();

        if (!$member) {
            return sendResponse(false, 'Member not found for this organization.', null, null, null, 404);
        }

        $denyOrgAdmin = $this->denyUnlessCanManageOrgAdminTarget($request, $organization, $member);
        if ($denyOrgAdmin) {
            return $denyOrgAdmin;
        }

        return sendResponse(true, 'Member retrieved successfully.', [
            'organization' => [
                'id' => $organization->id,
                'name' => $organization->name,
            ],
            'member' => $member,
        ]);
    }

    /**
     * Step 3.3: Invite member + send activation link.
     */
    public function invite(Request $request)
    {
        $organization = $this->resolveOrganization($request);
        $deny = $this->denyUnlessCan($request, 'member.invite');
        if ($deny) {
            return $deny;
        }

        if (!$organization) {
            return sendResponse(false, 'No active organization context found.', null, null, null, 422);
        }

        $organization->loadMissing('mailSetting');
        if (!$organization->mailSetting) {
            return sendResponse(
                false,
                'Your organization has not configured outbound email yet. Open Profile, complete Organization email settings (SMTP username, app password, and from address), save, then try inviting again.',
                ['requires_organization_mail' => true],
                null,
                null,
                422
            );
        }

        $invitationMailerName = OrganizationMailerRegistry::register($organization);
        if (!$invitationMailerName) {
            return sendResponse(
                false,
                'Organization email settings could not be applied. Please open Profile, review Organization email settings, save again, then retry the invitation.',
                ['requires_organization_mail' => true],
                null,
                null,
                422
            );
        }

        $orgMail = $organization->mailSetting;
        $invitationFromAddress = (string) $orgMail->mail_from_address;
        $invitationFromName = (string) ($orgMail->mail_from_name ?: $organization->name);

        $validator = validator($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            // Current schema requires unique/non-null phone.
            'phone' => 'required|string|max:20|unique:users,phone',
            'role_id' => 'required|integer|exists:roles,id',
        ]);

        if ($validator->fails()) {
            return simpleValidate($validator);
        }

        $role = Role::where('id', (int) $request->input('role_id'))
            ->where('scope', 'organization')
            ->where('is_active', true)
            ->first();

        if (!$role) {
            return sendResponse(false, 'Invalid organization role selected.', null, null, null, 422);
        }

        $platformMemberRole = Role::where('scope', 'platform')->where('key', 'member')->first();
        if (!$platformMemberRole) {
            return sendResponse(false, 'platform member role is not seeded yet. Run RoleSeeder first.', null, null, null, 422);
        }

        $user = User::create([
            'name' => $request->input('name'),
            'email' => $request->input('email'),
            'phone' => $request->input('phone'),
            // temporary password; user sets real password via reset-link activation.
            'password' => Str::random(24),
            'role_id' => $platformMemberRole->id, // platform member
        ]);

        DB::table('organization_user')->insert([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'role_id' => $role->id,
            'status' => 'invited',
            'invited_at' => now(),
            'accepted_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $status = Password::broker()->sendResetLink(
            ['email' => $user->email],
            function ($invitedUser, string $token) use (
                $organization,
                $request,
                $invitationMailerName,
                $invitationFromAddress,
                $invitationFromName
            ) {
                $inviteUrl = url(route('password.reset', [
                    'token' => $token,
                    'email' => $invitedUser->getEmailForPasswordReset(),
                    'invitation' => '1',
                ], false));

                Log::info('Team member invitation accept link (same token as email)', [
                    'organization_id' => $organization->id,
                    'user_id' => $invitedUser->id,
                    'email' => $invitedUser->getEmailForPasswordReset(),
                    'url' => $inviteUrl,
                ]);

                $inviter = $request->user();
                $invitedUser->notify(new OrganizationTeamInvitationNotification(
                    $token,
                    (string) $organization->name,
                    $inviter ? (string) $inviter->name : 'Your organization',
                    $this->inviterOrganizationRoleLabel($request, $organization),
                    $invitationMailerName,
                    $invitationFromAddress,
                    $invitationFromName,
                ));
                Event::dispatch(new PasswordResetLinkSent($invitedUser));
            }
        );

        if ($status !== Password::RESET_LINK_SENT) {
            return sendResponse(false, 'Member invited, but activation email could not be sent.', [
                'user_id' => $user->id,
                'email' => $user->email,
                'mail_status' => $status,
            ], null, null, 422);
        }

        return sendResponse(true, 'Invitation sent successfully. The invitee will receive an email with a link to accept.', [
            'user_id' => $user->id,
            'email' => $user->email,
            'organization_id' => $organization->id,
            'role_id' => $role->id,
        ]);
    }

    /**
     * Step 3.5: update organization role assignment.
     */
    public function updateRole(Request $request)
    {
        $organization = $this->resolveOrganization($request);
        $deny = $this->denyUnlessCan($request, 'member.role.assign');
        if ($deny) {
            return $deny;
        }

        if (!$organization) {
            return sendResponse(false, 'No active organization context found.', null, null, null, 422);
        }

        $validator = validator($request->all(), [
            'membership_id' => 'required|integer',
            'role_id' => 'required|integer|exists:roles,id',
        ]);

        if ($validator->fails()) {
            return simpleValidate($validator);
        }

        $membership = DB::table('organization_user')
            ->where('id', (int) $request->input('membership_id'))
            ->where('organization_id', $organization->id)
            ->first();

        if (!$membership) {
            return sendResponse(false, 'Membership not found for this organization.', null, null, null, 404);
        }

        $denyOrgAdmin = $this->denyUnlessCanManageOrgAdminTarget($request, $organization, $membership);
        if ($denyOrgAdmin) {
            return $denyOrgAdmin;
        }

        if (!$this->mayActorChangeTargetOrgRole($request, $organization, (int) $membership->user_id)) {
            return sendResponse(
                false,
                'You cannot change your own organization role. An organization administrator can update it for you.',
                null,
                null,
                null,
                422
            );
        }

        $role = Role::query()
            ->where('id', (int) $request->input('role_id'))
            ->where('scope', 'organization')
            ->where('is_active', true)
            ->first();

        if (!$role) {
            return sendResponse(false, 'Invalid organization role selected.', null, null, null, 422);
        }

        DB::table('organization_user')
            ->where('id', $membership->id)
            ->update([
                'role_id' => $role->id,
                'updated_at' => now(),
            ]);

        return sendResponse(true, 'Member role updated successfully!', [
            'membership_id' => $membership->id,
            'role_id' => $role->id,
            'role_name' => $role->name,
        ]);
    }

    /**
     * Edit member profile + org role (Org Owner/Admin).
     */
    public function updateMember(Request $request)
    {
        $organization = $this->resolveOrganization($request);
        if (!$organization) {
            return sendResponse(false, 'No active organization context found.', null, null, null, 422);
        }
        if (!$this->canViewOrEditTeamMemberDetails($request, $organization)) {
            return sendResponse(false, 'You are not allowed to edit this team member.', null, null, null, 403);
        }

        $validator = validator($request->all(), [
            'membership_id' => 'required|integer',
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'required|string|max:20',
            'role_id' => 'required|integer|exists:roles,id',
            'status' => 'required|string|in:active,deactivated',
        ]);
        if ($validator->fails()) {
            return simpleValidate($validator);
        }

        $membership = DB::table('organization_user')
            ->where('id', (int) $request->input('membership_id'))
            ->where('organization_id', $organization->id)
            ->first();
        if (!$membership) {
            return sendResponse(false, 'Membership not found for this organization.', null, null, null, 404);
        }

        $denyOrgAdmin = $this->denyUnlessCanManageOrgAdminTarget($request, $organization, $membership);
        if ($denyOrgAdmin) {
            return $denyOrgAdmin;
        }

        $targetUser = User::query()->find((int) $membership->user_id);
        if (!$targetUser) {
            return sendResponse(false, 'User not found.', null, null, null, 404);
        }

        $newRoleId = (int) $request->input('role_id');
        $currentRoleId = (int) ($membership->role_id ?? 0);
        $isSelf = (int) $membership->user_id === (int) $request->user()->id;
        if ($isSelf && $newRoleId !== $currentRoleId && !Gate::forUser($request->user())->allows('org.member.assign_own_org_role', $organization)) {
            return sendResponse(
                false,
                'You cannot change your own organization role. An organization administrator can update it for you.',
                null,
                null,
                null,
                422
            );
        }

        $role = Role::query()
            ->where('id', $newRoleId)
            ->where('scope', 'organization')
            ->where('is_active', true)
            ->first();
        if (!$role) {
            return sendResponse(false, 'Invalid organization role selected.', null, null, null, 422);
        }

        $emailExists = User::query()
            ->where('email', $request->input('email'))
            ->where('id', '!=', $targetUser->id)
            ->exists();
        if ($emailExists) {
            return sendResponse(false, 'Email is already in use by another user.', null, null, null, 422);
        }

        $phoneExists = User::query()
            ->where('phone', $request->input('phone'))
            ->where('id', '!=', $targetUser->id)
            ->exists();
        if ($phoneExists) {
            return sendResponse(false, 'Phone is already in use by another user.', null, null, null, 422);
        }

        DB::transaction(function () use ($targetUser, $membership, $request, $role) {
            $requestedStatus = (string) $request->input('status', 'active');
            $membershipStatus = $requestedStatus === 'active' ? 'active' : 'suspended';

            $targetUser->name = (string) $request->input('name');
            $targetUser->email = (string) $request->input('email');
            $targetUser->phone = (string) $request->input('phone');
            $targetUser->save();

            DB::table('organization_user')
                ->where('id', $membership->id)
                ->update([
                    'role_id' => $role->id,
                    'status' => $membershipStatus,
                    'accepted_at' => $membershipStatus === 'active' ? ($membership->accepted_at ?: now()) : $membership->accepted_at,
                    'updated_at' => now(),
                ]);
        });

        return sendResponse(true, 'Member updated successfully.', [
            'membership_id' => (int) $membership->id,
            'user_id' => (int) $targetUser->id,
            'role_id' => (int) $role->id,
            'role_name' => $role->name,
        ]);
    }

    /**
     * Manual activation for invited members (Org Owner/Admin).
     */
    public function activateMember(Request $request)
    {
        $organization = $this->resolveOrganization($request);
        if (!$organization) {
            return sendResponse(false, 'No active organization context found.', null, null, null, 422);
        }
        $actor = $request->user();
        if (!$actor || !OrganizationAccess::canUserFullyManageTeam($actor, $organization)) {
            return sendResponse(false, 'Only organization owner/admin can manually activate invited members.', null, null, null, 403);
        }
        $deny = $this->denyUnlessCan($request, 'member.activate_complete');
        if ($deny) {
            return $deny;
        }

        $validator = validator($request->all(), [
            'membership_id' => 'required|integer',
        ]);
        if ($validator->fails()) {
            return simpleValidate($validator);
        }

        $membership = DB::table('organization_user')
            ->where('id', (int) $request->input('membership_id'))
            ->where('organization_id', $organization->id)
            ->first();
        if (!$membership) {
            return sendResponse(false, 'Membership not found for this organization.', null, null, null, 404);
        }
        if ($membership->deleted_at) {
            return sendResponse(false, 'Archived member cannot be manually activated.', null, null, null, 422);
        }
        if (($membership->status ?? '') !== 'invited') {
            return sendResponse(false, 'Manual activation is allowed only for invited members.', null, null, null, 422);
        }

        $denyOrgAdmin = $this->denyUnlessCanManageOrgAdminTarget($request, $organization, $membership);
        if ($denyOrgAdmin) {
            return $denyOrgAdmin;
        }

        DB::transaction(function () use ($membership, $organization) {
            $targetUser = User::query()->find((int) $membership->user_id);
            if ($targetUser) {
                // Requested default credential for manual activation flow.
                $targetUser->password = 'password123';
                $targetUser->save();
            }

            DB::table('organization_user')
                ->where('id', $membership->id)
                ->update([
                    'status' => 'active',
                    'accepted_at' => $membership->accepted_at ?: now(),
                    'updated_at' => now(),
                ]);

            OrganizationAccess::migrateUserOrganizationScopedData(
                (int) $membership->user_id,
                null,
                (int) $organization->id
            );
        });

        return sendResponse(true, 'Member activated successfully.', [
            'membership_id' => (int) $membership->id,
            'user_id' => (int) $membership->user_id,
            'status' => 'active',
        ]);
    }

    /**
     * Step 3.6: soft-delete member + cascade soft-delete related records.
     */
    public function softDeleteMember(Request $request)
    {
        $organization = $this->resolveOrganization($request);
        $deny = $this->denyUnlessCan($request, 'member.soft_delete');
        if ($deny) {
            return $deny;
        }

        if (!$organization) {
            return sendResponse(false, 'No active organization context found.', null, null, null, 422);
        }

        $validator = validator($request->all(), [
            'membership_id' => 'required|integer',
        ]);
        if ($validator->fails()) {
            return simpleValidate($validator);
        }

        $membership = DB::table('organization_user')
            ->where('id', (int) $request->input('membership_id'))
            ->where('organization_id', $organization->id)
            ->first();
        if (!$membership) {
            return sendResponse(false, 'Membership not found for this organization.', null, null, null, 404);
        }
        if ($membership->deleted_at) {
            return sendResponse(false, 'Member is already archived.', null, null, null, 422);
        }

        $targetUser = User::find((int) $membership->user_id);
        if (!$targetUser) {
            return sendResponse(false, 'User not found.', null, null, null, 404);
        }

        $actor = $request->user();
        if ((int) $actor->id === (int) $targetUser->id) {
            return sendResponse(false, 'You cannot archive your own account from team settings.', null, null, null, 422);
        }
        if ((int) $organization->primary_user_id === (int) $targetUser->id) {
            return sendResponse(false, 'Primary organization owner cannot be archived from this action.', null, null, null, 422);
        }

        $denyOrgAdmin = $this->denyUnlessCanManageOrgAdminTarget($request, $organization, $membership);
        if ($denyOrgAdmin) {
            return $denyOrgAdmin;
        }

        DB::transaction(function () use ($organization, $membership, $targetUser) {
            $now = now();

            // Mark membership archived.
            DB::table('organization_user')
                ->where('id', $membership->id)
                ->update([
                    'status' => 'suspended',
                    'deleted_at' => $now,
                    'updated_at' => $now,
                ]);

            // Soft-delete user-scoped records for this org context.
            Angle::query()
                ->where('user_id', $targetUser->id)
                ->where('organization_id', $organization->id)
                ->delete();

            AngleTemplate::query()
                ->where('user_id', $targetUser->id)
                ->where('organization_id', $organization->id)
                ->delete();

            \App\Models\ThankYouPage::query()
                ->where('user_id', $targetUser->id)
                ->where('organization_id', $organization->id)
                ->delete();

            UserApiInstance::query()
                ->where('user_id', $targetUser->id)
                ->where('organization_id', $organization->id)
                ->delete();

            OtpServiceCredential::query()
                ->where('user_id', $targetUser->id)
                ->delete();

            UserApiCredential::query()
                ->where('user_id', $targetUser->id)
                ->delete();

            // Soft-delete user account itself.
            $targetUser->delete();
        });

        return sendResponse(true, 'Member archived successfully.', [
            'membership_id' => (int) $membership->id,
            'user_id' => (int) $membership->user_id,
        ]);
    }

    /**
     * Step 3.7: restore archived member and related records.
     */
    public function restoreMember(Request $request)
    {
        $organization = $this->resolveOrganization($request);
        $deny = $this->denyUnlessCan($request, 'member.restore');
        if ($deny) {
            return $deny;
        }

        if (!$organization) {
            return sendResponse(false, 'No active organization context found.', null, null, null, 422);
        }

        $validator = validator($request->all(), [
            'membership_id' => 'required|integer',
        ]);
        if ($validator->fails()) {
            return simpleValidate($validator);
        }

        $membership = DB::table('organization_user')
            ->where('id', (int) $request->input('membership_id'))
            ->where('organization_id', $organization->id)
            ->first();
        if (!$membership) {
            return sendResponse(false, 'Membership not found for this organization.', null, null, null, 404);
        }
        if (!$membership->deleted_at) {
            return sendResponse(false, 'Member is not archived.', null, null, null, 422);
        }

        $denyOrgAdmin = $this->denyUnlessCanManageOrgAdminTarget($request, $organization, $membership);
        if ($denyOrgAdmin) {
            return $denyOrgAdmin;
        }

        $targetUser = User::withTrashed()->find((int) $membership->user_id);
        if (!$targetUser) {
            return sendResponse(false, 'User not found for restore.', null, null, null, 404);
        }

        DB::transaction(function () use ($organization, $membership, $targetUser) {
            $now = now();

            // Restore user account.
            if (method_exists($targetUser, 'restore')) {
                $targetUser->restore();
            }

            // Restore membership.
            DB::table('organization_user')
                ->where('id', $membership->id)
                ->update([
                    'status' => 'active',
                    'deleted_at' => null,
                    'accepted_at' => $membership->accepted_at ?: $now,
                    'updated_at' => $now,
                ]);

            Angle::withTrashed()
                ->where('user_id', $targetUser->id)
                ->where('organization_id', $organization->id)
                ->restore();

            AngleTemplate::withTrashed()
                ->where('user_id', $targetUser->id)
                ->where('organization_id', $organization->id)
                ->restore();

            \App\Models\ThankYouPage::withTrashed()
                ->where('user_id', $targetUser->id)
                ->where('organization_id', $organization->id)
                ->restore();

            UserApiInstance::withTrashed()
                ->where('user_id', $targetUser->id)
                ->where('organization_id', $organization->id)
                ->restore();

            OtpServiceCredential::withTrashed()
                ->where('user_id', $targetUser->id)
                ->restore();

            UserApiCredential::withTrashed()
                ->where('user_id', $targetUser->id)
                ->restore();
        });

        return sendResponse(true, 'Member restored successfully.', [
            'membership_id' => (int) $membership->id,
            'user_id' => (int) $membership->user_id,
        ]);
    }
}

