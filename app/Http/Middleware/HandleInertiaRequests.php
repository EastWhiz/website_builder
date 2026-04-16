<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $orgTeamAdmin = false;
        if ($user) {
            if ((int) ($user->role_id ?? 0) === 1 || (($user->role->name ?? null) === 'admin')) {
                $orgTeamAdmin = true;
            } else {
                $org = $user->currentOrganization();
                if ($org) {
                    if ((int) $org->primary_user_id === (int) $user->id) {
                        $orgTeamAdmin = true;
                    } else {
                        $roleKey = DB::table('organization_user as ou')
                            ->leftJoin('roles as r', 'r.id', '=', 'ou.role_id')
                            ->where('ou.organization_id', $org->id)
                            ->where('ou.user_id', $user->id)
                            ->whereNull('ou.deleted_at')
                            ->where('ou.status', 'active')
                            ->value('r.key');
                        $orgTeamAdmin = ($roleKey === 'org_admin');
                    }
                }
            }
        }

        $canAssignOwnOrgRole = false;
        if ($user && ($currentOrg = $user->currentOrganization())) {
            $canAssignOwnOrgRole = Gate::forUser($user)->allows('org.member.assign_own_org_role', $currentOrg);
        }

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user,
                'permissions' => $user ? [
                    'org_role_crud' => Gate::forUser($user)->allows('org.role.crud'),
                    'permission_matrix_update' => Gate::forUser($user)->allows('org.permission.matrix.update'),
                    'member_invite' => Gate::forUser($user)->allows('org.permission', 'member.invite'),
                    'member_role_assign' => Gate::forUser($user)->allows('org.permission', 'member.role.assign'),
                    'member_edit' => Gate::forUser($user)->allows('org.permission', 'member.edit'),
                    'member_soft_delete' => Gate::forUser($user)->allows('org.permission', 'member.soft_delete'),
                    'member_restore' => Gate::forUser($user)->allows('org.permission', 'member.restore'),
                    'member_activate_complete' => Gate::forUser($user)->allows('org.permission', 'member.activate_complete'),
                    'role_view' => Gate::forUser($user)->allows('org.permission', 'role.view'),
                    'content_transfer_in_org' => Gate::forUser($user)->allows('org.permission', 'content.transfer_in_org'),
                    'content_clone_cross_org' => Gate::forUser($user)->allows('org.permission', 'content.clone_cross_org'),
                    'audit_view_org' => Gate::forUser($user)->allows('org.permission', 'audit.view_org'),
                    'audit_view_cross_org' => Gate::forUser($user)->allows('org.permission', 'audit.view_cross_org'),
                    'org_team_admin' => $orgTeamAdmin,
                    'can_assign_own_org_role' => $canAssignOwnOrgRole,
                ] : [],
            ],
        ];
    }
}
