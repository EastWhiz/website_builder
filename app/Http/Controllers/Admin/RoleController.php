<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\RolePermission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

class RoleController extends Controller
{
    private function denyUnlessRoleCrud()
    {
        if (!Gate::forUser(Auth::user())->allows('org.role.crud')) {
            return sendResponse(false, 'Unauthorized action.', null, null, null, 403);
        }
        return null;
    }

    private function isPrivilegedPlatformAdmin(): bool
    {
        $user = Auth::user();
        if (!$user) {
            return false;
        }

        return (int) ($user->role_id ?? 0) === 1 || (($user->role->name ?? null) === 'admin');
    }

    private function rejectIfSystemRole(Role $role)
    {
        // Privileged platform admins can bypass role limitations as requested.
        if ($this->isPrivilegedPlatformAdmin()) {
            return null;
        }

        // Step 3B.3 default guard for non-privileged actors.
        if ((bool) $role->is_system) {
            return sendResponse(false, 'System roles cannot be edited or archived.', null, null, null, 422);
        }

        return null;
    }

    private function allowedPermissionKeys(): array
    {
        // Phase 0 lock: keep in sync with `permission-matrix-v1.txt`
        return [
            // Organization & Team
            'org.create',
            'org.update_status',
            'member.invite',
            'member.activate_complete',
            'member.role.assign',
            'member.soft_delete',
            'member.restore',

            // Role Management
            'role.view',
            'role.create',
            'role.update',
            'role.archive',

            // Content
            'content.view_own',
            'content.view_org_all',
            'content.create',
            'content.update_own',
            'content.update_org_all',
            'content.soft_delete',
            'content.transfer_in_org',
            'content.move_cross_org',
            'content.clone_cross_org',

            // Integrations
            'integration.catalog.create',
            'integration.catalog.update',
            'integration.catalog.archive',
            'integration.instance.view_own',
            'integration.instance.view_org',
            'integration.instance.create',
            'integration.instance.update',
            'integration.instance.soft_del',

            // Audit & Logs
            'audit.view_org',
            'audit.view_cross_org',

            // Permission Matrix / Policy Management
            'permission.matrix.view',
            'permission.matrix.update',
        ];
    }

    private function validatePermissionKeys(array $permissions)
    {
        $allowed = array_flip($this->allowedPermissionKeys());
        $invalid = [];

        foreach ($permissions as $p) {
            $key = trim((string) $p);
            if ($key === '') {
                continue;
            }
            if (!isset($allowed[$key])) {
                $invalid[] = $key;
            }
        }

        if (!empty($invalid)) {
            return sendResponse(false, 'Invalid permission keys provided.', [
                'invalid_permissions' => array_values(array_unique($invalid)),
            ], null, null, 422);
        }

        return null;
    }

    public function index(Request $request)
    {
        $deny = $this->denyUnlessRoleCrud();
        if ($deny) {
            return $deny;
        }

        $scope = trim((string) $request->get('scope', 'organization'));
        if (!in_array($scope, ['organization', 'platform'], true)) {
            $scope = 'organization';
        }

        $roles = Role::query()
            ->where('scope', $scope)
            ->orderByDesc('is_active')
            ->orderBy('is_system', 'desc')
            ->orderBy('name')
            ->with(['permissions:id,role_id,permission_key'])
            ->get()
            ->map(function (Role $role) {
                return [
                    'id' => $role->id,
                    'scope' => $role->scope,
                    'key' => $role->key,
                    'name' => $role->name,
                    'description' => $role->description,
                    'is_system' => (bool) $role->is_system,
                    'is_active' => (bool) $role->is_active,
                    'created_by' => $role->created_by,
                    'created_at' => $role->created_at,
                    'updated_at' => $role->updated_at,
                    'permissions' => $role->permissions->pluck('permission_key')->values(),
                ];
            });

        return sendResponse(true, 'Roles retrieved successfully!', $roles);
    }

    public function store(Request $request)
    {
        $deny = $this->denyUnlessRoleCrud();
        if ($deny) {
            return $deny;
        }

        $validator = Validator::make($request->all(), [
            'scope' => 'required|string|in:organization,platform',
            'key' => 'required|string|max:80',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'is_active' => 'nullable|boolean',
            'permissions' => 'nullable|array',
            'permissions.*' => 'string|max:255',
        ]);
        if ($validator->fails()) {
            return simpleValidate($validator);
        }

        $permissions = $request->input('permissions', []);
        if (!is_array($permissions)) {
            $permissions = [];
        }
        $invalidResponse = $this->validatePermissionKeys($permissions);
        if ($invalidResponse) {
            return $invalidResponse;
        }

        $role = DB::transaction(function () use ($request, $permissions) {
            /** @var \App\Models\Role $role */
            $role = Role::create([
                'scope' => $request->input('scope'),
                'key' => trim((string) $request->input('key')),
                'name' => trim((string) $request->input('name')),
                'description' => $request->input('description'),
                'is_system' => false,
                'is_active' => (bool) $request->input('is_active', true),
                'created_by' => Auth::id(),
            ]);

            $uniquePerms = array_values(array_unique(array_map('strval', $permissions)));
            foreach ($uniquePerms as $permKey) {
                $permKey = trim($permKey);
                if ($permKey === '') {
                    continue;
                }
                RolePermission::create([
                    'role_id' => $role->id,
                    'permission_key' => $permKey,
                ]);
            }

            return $role;
        });

        return sendResponse(true, 'Role created successfully!', $role->fresh()->load('permissions:id,role_id,permission_key'));
    }

    public function update(Request $request, int $id)
    {
        $deny = $this->denyUnlessRoleCrud();
        if ($deny) {
            return $deny;
        }

        $role = Role::with('permissions:id,role_id,permission_key')->findOrFail($id);
        $systemGuard = $this->rejectIfSystemRole($role);
        if ($systemGuard) {
            return $systemGuard;
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'is_active' => 'nullable|boolean',
            'permissions' => 'nullable|array',
            'permissions.*' => 'string|max:255',
        ]);
        if ($validator->fails()) {
            return simpleValidate($validator);
        }

        $permissions = $request->input('permissions', []);
        if (!is_array($permissions)) {
            $permissions = [];
        }
        $invalidResponse = $this->validatePermissionKeys($permissions);
        if ($invalidResponse) {
            return $invalidResponse;
        }

        DB::transaction(function () use ($role, $request, $permissions) {
            $role->name = trim((string) $request->input('name'));
            $role->description = $request->input('description');
            if ($request->has('is_active')) {
                $role->is_active = (bool) $request->input('is_active');
            }
            $role->save();

            RolePermission::query()->where('role_id', $role->id)->delete();
            $uniquePerms = array_values(array_unique(array_map('strval', $permissions)));
            foreach ($uniquePerms as $permKey) {
                $permKey = trim($permKey);
                if ($permKey === '') {
                    continue;
                }
                RolePermission::create([
                    'role_id' => $role->id,
                    'permission_key' => $permKey,
                ]);
            }
        });

        return sendResponse(true, 'Role updated successfully!', $role->fresh()->load('permissions:id,role_id,permission_key'));
    }

    public function archive(Request $request, int $id)
    {
        $deny = $this->denyUnlessRoleCrud();
        if ($deny) {
            return $deny;
        }

        $role = Role::findOrFail($id);
        $systemGuard = $this->rejectIfSystemRole($role);
        if ($systemGuard) {
            return $systemGuard;
        }

        $role->is_active = false;
        $role->save();

        return sendResponse(true, 'Role archived successfully!', $role);
    }
}

