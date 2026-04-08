<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\OrganizationActivityLog;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class OrganizationController extends Controller
{
    private function logOrgAction(?int $organizationId, string $action, array $metadata = []): void
    {
        try {
            OrganizationActivityLog::create([
                'organization_id' => $organizationId,
                'actor_user_id' => Auth::id(),
                'action' => $action,
                'metadata' => $metadata,
            ]);
        } catch (\Throwable $e) {
            // Logging should never break primary admin actions.
        }
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

        $query = Organization::query()->with('owner:id,name,email');

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
            'status' => 'nullable|string|in:active,on_hold,deactivated',
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

        $this->logOrgAction($org->id, 'org.create', [
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
            'org_status' => 'nullable|string|in:active,on_hold,deactivated',
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

        $result = DB::transaction(function () use ($request, $orgAdminRole) {
            $org = Organization::create([
                'name' => $request->input('org_name'),
                'status' => $request->input('org_status', 'active'),
            ]);

            $owner = \App\Models\User::create([
                'name' => $request->input('owner_name'),
                'email' => $request->input('owner_email'),
                'phone' => $request->input('owner_phone'),
                'password' => $request->input('owner_password'),
                // Platform role: default member (2). Super Admin remains role_id=1.
                'role_id' => 2,
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

            return ['organization' => $org, 'owner' => $owner];
        });

        $this->logOrgAction($result['organization']->id ?? null, 'org.provision', [
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
            'status' => 'required|string|in:active,on_hold,deactivated',
        ]);

        if ($validator->fails()) {
            return simpleValidate($validator);
        }

        $org = Organization::findOrFail($id);
        $from = $org->status;
        $org->status = $request->input('status');
        $org->save();

        $this->logOrgAction($org->id, 'org.status.update', [
            'from' => $from,
            'to' => $org->status,
        ]);

        return sendResponse(true, 'Organization status updated successfully!', $org);
    }
}

