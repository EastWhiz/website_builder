<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('api_categories') || Schema::hasColumn('api_categories', 'integration_group')) {
            return;
        }

        Schema::table('api_categories', function (Blueprint $table) {
            $table->string('integration_group', 20)->default('network')->after('name');
            $table->index('integration_group');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('api_categories') || !Schema::hasColumn('api_categories', 'integration_group')) {
            return;
        }

        Schema::table('api_categories', function (Blueprint $table) {
            $table->dropIndex(['integration_group']);
            $table->dropColumn('integration_group');
        });
    }
};

