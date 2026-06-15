<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $roleIds = DB::table('roles')
            ->whereIn('key', ['org_admin', 'org_manager'])
            ->pluck('id');

        $now = now();
        foreach ($roleIds as $roleId) {
            foreach (['content.create', 'content.update_own', 'content.update_org_all'] as $permissionKey) {
                DB::table('role_permissions')->updateOrInsert(
                    [
                        'role_id' => (int) $roleId,
                        'permission_key' => $permissionKey,
                    ],
                    [
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]
                );
            }
        }
    }

    public function down(): void
    {
        $roleIds = DB::table('roles')
            ->whereIn('key', ['org_admin', 'org_manager'])
            ->pluck('id');

        DB::table('role_permissions')
            ->whereIn('role_id', $roleIds)
            ->whereIn('permission_key', ['content.create', 'content.update_own', 'content.update_org_all'])
            ->delete();
    }
};
