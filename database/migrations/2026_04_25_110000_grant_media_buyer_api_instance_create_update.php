<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Grant media_buyer default ability to create/update own API instances.
     */
    public function up(): void
    {
        $roleId = DB::table('roles')->where('key', 'media_buyer')->value('id');
        if (!$roleId) {
            return;
        }

        $now = now();
        foreach (['integration.instance.create', 'integration.instance.update'] as $permissionKey) {
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

    /**
     * Revert media_buyer API instance create/update defaults.
     */
    public function down(): void
    {
        $roleId = DB::table('roles')->where('key', 'media_buyer')->value('id');
        if (!$roleId) {
            return;
        }

        DB::table('role_permissions')
            ->where('role_id', (int) $roleId)
            ->whereIn('permission_key', ['integration.instance.create', 'integration.instance.update'])
            ->delete();
    }
};

