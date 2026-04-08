<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Step 1.5: additional indexes/constraints for Phase 1.
     */
    public function up(): void
    {
        Schema::table('organization_user', function (Blueprint $table) {
            $table->foreign('role_id')->references('id')->on('roles')->nullOnDelete();
            $table->index(['organization_id', 'deleted_at'], 'org_user_org_deleted_idx');
        });

        Schema::table('roles', function (Blueprint $table) {
            $table->unique(['scope', 'key'], 'roles_scope_key_unique');
        });

        Schema::table('angles', function (Blueprint $table) {
            $table->index(['organization_id', 'deleted_at'], 'angles_org_deleted_idx');
        });

        Schema::table('angle_templates', function (Blueprint $table) {
            $table->index(['organization_id', 'deleted_at'], 'angle_templates_org_deleted_idx');
        });

        Schema::table('thank_you_pages', function (Blueprint $table) {
            $table->index(['organization_id', 'deleted_at'], 'thank_you_pages_org_deleted_idx');
        });

        Schema::table('user_api_instances', function (Blueprint $table) {
            $table->index(['organization_id', 'deleted_at'], 'uai_org_deleted_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_api_instances', function (Blueprint $table) {
            $table->dropIndex('uai_org_deleted_idx');
        });

        Schema::table('thank_you_pages', function (Blueprint $table) {
            $table->dropIndex('thank_you_pages_org_deleted_idx');
        });

        Schema::table('angle_templates', function (Blueprint $table) {
            $table->dropIndex('angle_templates_org_deleted_idx');
        });

        Schema::table('angles', function (Blueprint $table) {
            $table->dropIndex('angles_org_deleted_idx');
        });

        Schema::table('roles', function (Blueprint $table) {
            $table->dropUnique('roles_scope_key_unique');
        });

        Schema::table('organization_user', function (Blueprint $table) {
            $table->dropIndex('org_user_org_deleted_idx');
            $table->dropForeign(['role_id']);
        });
    }
};

