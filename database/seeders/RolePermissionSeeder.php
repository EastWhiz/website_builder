<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissionsByRole = [
            'org_admin' => [
                'member.invite',
                'member.role.assign',
                'member.soft_delete',
                'member.restore',
                'permission.matrix.view',
                'permission.matrix.update',
                'content.view_org_all',
                'content.transfer_in_org',
                'integration.instance.view_org',
                'integration.instance.create',
                'integration.instance.update',
                'integration.instance.soft_delete',
                'audit.view_org',
            ],
            'org_manager' => [
                'member.invite',
                'member.role.assign',
                'member.soft_delete',
                'member.restore',
                'permission.matrix.view',
                // permission.matrix.update is delegated/conditional by policy.
                'content.view_org_all',
                'content.transfer_in_org',
                'integration.instance.view_org',
                'integration.instance.create',
                'integration.instance.update',
                'integration.instance.soft_delete',
                'audit.view_org',
            ],
            'media_buyer' => [
                'content.view_own',
                'content.create',
                'content.update_own',
                'integration.instance.view_own',
            ],
            'admin' => [
                'role.view',
                'role.create',
                'role.update',
                'role.archive',
                'integration.catalog.create',
                'integration.catalog.update',
                'integration.catalog.archive',
                'content.move_cross_org',
                'content.clone_cross_org',
                'audit.view_cross_org',
            ],
        ];

        foreach ($permissionsByRole as $roleKey => $permissionKeys) {
            $role = Role::where('key', $roleKey)->first();
            if (!$role) {
                continue;
            }

            foreach ($permissionKeys as $permissionKey) {
                DB::table('role_permissions')->updateOrInsert(
                    [
                        'role_id' => $role->id,
                        'permission_key' => $permissionKey,
                    ],
                    [
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }
        }
    }
}

