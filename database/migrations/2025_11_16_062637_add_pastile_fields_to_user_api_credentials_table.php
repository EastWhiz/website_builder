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
        Schema::table('user_api_credentials', function (Blueprint $table) {
            // Add Pastile (tb.pastile.net) API credentials
            $table->string('pastile_username')->nullable()->after('koi_api_key');
            $table->text('pastile_password')->nullable()->after('pastile_username');
            $table->text('pastile_api_key')->nullable()->after('pastile_password');
            $table->string('pastile_ai')->nullable()->after('pastile_api_key'); // ai parameter
            $table->string('pastile_ci')->nullable()->after('pastile_ai'); // ci parameter
            $table->string('pastile_gi')->nullable()->after('pastile_ci'); // gi parameter
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_api_credentials', function (Blueprint $table) {
            $table->dropColumn([
                'pastile_username',
                'pastile_password',
                'pastile_api_key',
                'pastile_ai',
                'pastile_ci',
                'pastile_gi'
            ]);
        });
    }
};
