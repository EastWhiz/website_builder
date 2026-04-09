<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
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
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user,
                'permissions' => $user ? [
                    'org_role_crud' => Gate::forUser($user)->allows('org.role.crud'),
                    'permission_matrix_update' => Gate::forUser($user)->allows('org.permission.matrix.update'),
                    'member_invite' => Gate::forUser($user)->allows('org.permission', 'member.invite'),
                    'member_role_assign' => Gate::forUser($user)->allows('org.permission', 'member.role.assign'),
                    'member_soft_delete' => Gate::forUser($user)->allows('org.permission', 'member.soft_delete'),
                    'member_restore' => Gate::forUser($user)->allows('org.permission', 'member.restore'),
                    'member_activate_complete' => Gate::forUser($user)->allows('org.permission', 'member.activate_complete'),
                    'role_view' => Gate::forUser($user)->allows('org.permission', 'role.view'),
                ] : [],
            ],
        ];
    }
}
