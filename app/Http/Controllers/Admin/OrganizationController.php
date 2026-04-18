<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TransferOrganizationMemberRequest;
use App\Http\Requests\Admin\ValidateOrganizationMemberTransferRequest;
use App\Models\Organization;
use App\Models\Role;
use App\Services\OrganizationActivityLogger;
use App\Support\OrganizationAccess;
use App\Services\OrganizationMemberTransferValidator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class OrganizationController extends Controller
{
    public function __construct(private readonly OrganizationActivityLogger $activityLogger)
    {
    }

    public function index(Request $request)
    {
        $pageCount = (int) ($request->get('page_count', 10));
        if ($pageCount <= 0) {
            $pageCount = 10;
        }
        if ($pageCount > 100) {
            $pageCount = 100;
        }

        $query = Organization::query()->with('owner:id,name,email,phone');

        $q = trim((string) $request->get('q', ''));
        if ($q !== '') {
            $query->where(function ($sub) use ($q) {
                $sub->where('name', 'LIKE', '%' . $q . '%')
                    ->orWhereHas('owner', function ($o) use ($q) {
                        $o->where('email', 'LIKE', '%' . $q . '%')
                            ->orWhere('name', 'LIKE', '%' . $q . '%');
                    });
            });
        }

        $status = trim((string) $request->get('status', ''));
        if ($status !== '') {
            $query->where('status', $status);
        }

        $sort = trim((string) $request->get('sort', 'id desc'));
        if ($sort !== '') {
            [$col, $dir] = array_pad(explode(' ', $sort), 2, 'desc');
            $dir = strtolower($dir) === 'asc' ? 'asc' : 'desc';
            if (!in_array($col, ['id', 'name', 'status', 'created_at'], true)) {
                $col = 'id';
            }
            $query->orderBy($col, $dir);
        }

        $orgs = $query->cursorPaginate($pageCount);

        return sendResponse(true, 'Organizations retrieved successfully!', $orgs);
    }

    /**
     * Create a new organization (Super Admin only).
     *
     * Phase 2.1: API endpoint + validation (owner creation handled in Step 2.2).
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'status' => 'nullable|string|in:active,deactivated',
            'primary_user_id' => 'nullable|integer|exists:users,id',
        ]);

        if ($validator->fails()) {
            return simpleValidate($validator);
        }

        $org = Organization::create([
            'name' => $request->input('name'),
            'status' => $request->input('status', 'active'),
            'primary_user_id' => $request->input('primary_user_id'),
        ]);

        $this->activityLogger->log($org->id, 'org.create', [
            'name' => $org->name,
            'status' => $org->status,
            'primary_user_id' => $org->primary_user_id,
        ]);

        return sendResponse(true, 'Organization created successfully!', $org);
    }

    /**
     * Provision an organization and its owner (Super Admin only).
     *
     * Phase 2.2: Create org + owner user + org_admin membership transactionally.
     */
    public function provision(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'org_name' => 'required|string|max:255',
            'org_status' => 'nullable|string|in:active,deactivated',
            'owner_name' => 'required|string|max:255',
            'owner_email' => 'required|email|max:255|unique:users,email',
            'owner_phone' => 'required|string|max:20|unique:users,phone',
            'owner_password' => 'required|string|min:8',
        ]);

        if ($validator->fails()) {
            return simpleValidate($validator);
        }

        $orgAdminRole = Role::where('scope', 'organization')->where('key', 'org_admin')->first();
        if (!$orgAdminRole) {
            return sendResponse(false, 'org_admin role is not seeded yet. Run RoleSeeder first.', null, null, null, 422);
        }

        $platformMemberRole = Role::where('scope', 'platform')->where('key', 'member')->first();
        if (!$platformMemberRole) {
            return sendResponse(false, 'platform member role is not seeded yet. Run RoleSeeder first.', null, null, null, 422);
        }

        $result = DB::transaction(function () use ($request, $orgAdminRole, $platformMemberRole) {
            $org = Organization::create([
                'name' => $request->input('org_name'),
                'status' => $request->input('org_status', 'active'),
            ]);

            $owner = \App\Models\User::create([
                'name' => $request->input('owner_name'),
                'email' => $request->input('owner_email'),
                'phone' => $request->input('owner_phone'),
                'password' => $request->input('owner_password'),
                // Platform role: default member (resolved by scope+key).
                'role_id' => $platformMemberRole->id,
            ]);

            $org->primary_user_id = $owner->id;
            $org->save();

            DB::table('organization_user')->insert([
                'organization_id' => $org->id,
                'user_id' => $owner->id,
                'role_id' => $orgAdminRole->id,
                'status' => 'active',
                'invited_at' => now(),
                'accepted_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            OrganizationAccess::migrateUserOrganizationScopedData((int) $owner->id, null, (int) $org->id);

            return ['organization' => $org, 'owner' => $owner];
        });

        $this->activityLogger->log($result['organization']->id ?? null, 'org.provision', [
            'org_name' => $request->input('org_name'),
            'org_status' => $request->input('org_status', 'active'),
            'owner_email' => $request->input('owner_email'),
            'owner_user_id' => $result['owner']->id ?? null,
        ]);

        return sendResponse(true, 'Organization and owner provisioned successfully!', $result);
    }

    /**
     * Update organization status (Super Admin only).
     *
     * Phase 2.4: hold/deactivate/reactivate controls.
     */
    public function updateStatus(Request $request, int $id)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|string|in:active,deactivated',
        ]);

        if ($validator->fails()) {
            return simpleValidate($validator);
        }

        $org = Organization::findOrFail($id);
        $from = $org->status;
        $org->status = $request->input('status');
        $org->save();

        $this->activityLogger->log($org->id, 'org.status.update', [
            'from' => $from,
            'to' => $org->status,
        ]);

        return sendResponse(true, 'Organization status updated successfully!', $org);
    }

    /**
     * View organization details (Super Admin only).
     */
    public function show(int $id)
    {
        $org = Organization::with('owner:id,name,email,phone')->findOrFail($id);
        return sendResponse(true, 'Organization retrieved successfully!', $org);
    }

    /**
     * Step 3C.1 preflight: validate user cross-org transfer preconditions.
     */
    public function validateMemberTransfer(
        ValidateOrganizationMemberTransferRequest $request,
        OrganizationMemberTransferValidator $validator
    ) {
        $validated = $request->validated();
        try {
            $context = $validator->validate(
                (int) $validated['user_id'],
                isset($validated['source_organization_id']) ? (int) $validated['source_organization_id'] : null,
                (int) $validated['target_organization_id']
            );
        } catch (ValidationException $e) {
            $message = collect($e->errors())->flatten()->first() ?: 'Transfer pre-check failed.';

            return sendResponse(false, (string) $message, [
                'errors' => $e->errors(),
            ], null, null, 422);
        }

        return sendResponse(true, 'Transfer pre-check passed. Member is eligible to move.', $context);
    }

    /**
     * Step 3C.2 + 3C.3: move membership in place and update org-scoped assets in a transaction.
     */
    public function transferMember(
        TransferOrganizationMemberRequest $request,
        OrganizationMemberTransferValidator $validator
    ) {
        $validated = $request->validated();
        try {
            $context = $validator->validate(
                (int) $validated['user_id'],
                isset($validated['source_organization_id']) ? (int) $validated['source_organization_id'] : null,
                (int) $validated['target_organization_id']
            );
        } catch (ValidationException $e) {
            $message = collect($e->errors())->flatten()->first() ?: 'Member transfer validation failed.';

            return sendResponse(false, (string) $message, [
                'errors' => $e->errors(),
            ], null, null, 422);
        }

        $fromOrgId = (int) ($context['source_organization']['id'] ?? 0);
        $toOrgId = (int) $context['target_organization']['id'];
        $userId = (int) $context['user']['id'];

        $updatedCounts = DB::transaction(function () use ($context, $fromOrgId, $toOrgId, $userId) {
            if (!empty($context['membership']['id'])) {
                DB::table('organization_user')
                    ->where('id', (int) $context['membership']['id'])
                    ->update([
                        'organization_id' => $toOrgId,
                        'role_id' => (int) $context['resolved_target_role']['id'],
                        'updated_at' => now(),
                    ]);
            } else {
                DB::table('organization_user')->insert([
                    'organization_id' => $toOrgId,
                    'user_id' => $userId,
                    'role_id' => (int) $context['resolved_target_role']['id'],
                    'status' => 'active',
                    'invited_at' => now(),
                    'accepted_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $fromOrgForMigrate = $fromOrgId > 0 ? $fromOrgId : null;

            return OrganizationAccess::migrateUserOrganizationScopedData($userId, $fromOrgForMigrate, $toOrgId);
        });

        $updatedMembership = DB::table('organization_user as ou')
            ->leftJoin('roles as r', 'r.id', '=', 'ou.role_id')
            ->where('ou.user_id', $userId)
            ->where('ou.organization_id', $toOrgId)
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

        $this->activityLogger->logMoveOrClone(
            $toOrgId,
            'org.member.transfer_cross_org',
            'organization_member',
            $userId,
            $fromOrgId > 0 ? $fromOrgId : null,
            $toOrgId,
            [
                'membership_id' => (int) ($updatedMembership->membership_id ?? 0),
                'from_role' => [
                    'id' => (int) ($context['membership']['role_id'] ?? 0),
                    'key' => (string) ($context['membership']['role_key'] ?? ''),
                    'name' => (string) ($context['membership']['role_name'] ?? ''),
                ],
                'to_role' => [
                    'id' => (int) ($context['resolved_target_role']['id'] ?? 0),
                    'key' => (string) ($context['resolved_target_role']['key'] ?? ''),
                    'name' => (string) ($context['resolved_target_role']['name'] ?? ''),
                ],
                'updated_assets' => $updatedCounts,
            ]
        );

        return sendResponse(true, 'Member moved to target organization successfully.', [
            'user' => $context['user'],
            'from_organization' => $context['source_organization'],
            'to_organization' => $context['target_organization'],
            'membership' => $updatedMembership,
            'updated_assets' => $updatedCounts,
            'mode' => $context['mode'],
        ]);
    }

    /**
     * Edit organization details (Super Admin only).
     */
    public function update(Request $request, int $id)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'status' => 'required|string|in:active,deactivated',
            'owner_name' => 'required|string|max:255',
            'owner_email' => 'required|email|max:255',
            'owner_phone' => 'required|string|max:20',
            'owner_password' => 'nullable|string|min:8',
        ]);

        if ($validator->fails()) {
            return simpleValidate($validator);
        }

        $org = Organization::with('owner')->findOrFail($id);
        if (!$org->owner) {
            return sendResponse(false, 'Organization owner not found.', null, null, null, 422);
        }

        $emailExists = \App\Models\User::query()
            ->where('email', $request->input('owner_email'))
            ->where('id', '!=', $org->owner->id)
            ->exists();
        if ($emailExists) {
            return sendResponse(false, 'Owner email is already in use by another user.', null, null, null, 422);
        }

        $phoneExists = \App\Models\User::query()
            ->where('phone', $request->input('owner_phone'))
            ->where('id', '!=', $org->owner->id)
            ->exists();
        if ($phoneExists) {
            return sendResponse(false, 'Owner phone is already in use by another user.', null, null, null, 422);
        }

        $before = [
            'name' => $org->name,
            'status' => $org->status,
            'owner_name' => $org->owner->name,
            'owner_email' => $org->owner->email,
            'owner_phone' => $org->owner->phone,
        ];

        $org->name = $request->input('name');
        $org->status = $request->input('status');
        $org->save();

        $org->owner->name = $request->input('owner_name');
        $org->owner->email = $request->input('owner_email');
        $org->owner->phone = $request->input('owner_phone');
        if ($request->filled('owner_password')) {
            $org->owner->password = $request->input('owner_password');
        }
        $org->owner->save();

        $this->activityLogger->log($org->id, 'org.update', [
            'from' => $before,
            'to' => [
                'name' => $org->name,
                'status' => $org->status,
                'owner_name' => $org->owner->name,
                'owner_email' => $org->owner->email,
                'owner_phone' => $org->owner->phone,
            ],
        ]);

        return sendResponse(true, 'Organization updated successfully!', $org->fresh()->load('owner:id,name,email,phone'));
    }
}

