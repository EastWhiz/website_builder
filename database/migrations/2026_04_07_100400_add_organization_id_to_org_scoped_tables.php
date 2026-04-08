<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Step 1.3: denormalize organization_id for org-scoped queries (nullable for legacy rows).
     */
    public function up(): void
    {
        if (!Schema::hasColumn('angles', 'organization_id')) {
            Schema::table('angles', function (Blueprint $table) {
                $table->unsignedBigInteger('organization_id')->nullable()->after('user_id');
            });
        }

        if (!Schema::hasColumn('angle_templates', 'organization_id')) {
            Schema::table('angle_templates', function (Blueprint $table) {
                $table->unsignedBigInteger('organization_id')->nullable()->after('user_id');
            });
        }

        if (!Schema::hasColumn('thank_you_pages', 'organization_id')) {
            Schema::table('thank_you_pages', function (Blueprint $table) {
                $table->unsignedBigInteger('organization_id')->nullable()->after('user_id');
            });
        }

        if (!Schema::hasColumn('user_api_instances', 'organization_id')) {
            Schema::table('user_api_instances', function (Blueprint $table) {
                $table->unsignedBigInteger('organization_id')->nullable()->after('user_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_api_instances', function (Blueprint $table) {
            $table->dropColumn('organization_id');
        });

        Schema::table('thank_you_pages', function (Blueprint $table) {
            $table->dropColumn('organization_id');
        });

        Schema::table('angle_templates', function (Blueprint $table) {
            $table->dropColumn('organization_id');
        });

        Schema::table('angles', function (Blueprint $table) {
            $table->dropColumn('organization_id');
        });
    }
};
