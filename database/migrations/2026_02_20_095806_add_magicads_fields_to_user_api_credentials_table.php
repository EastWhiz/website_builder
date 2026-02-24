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
            // Add MagicAds API credentials
            $table->string('magicads_username')->nullable()->after('nauta_api_token');
            $table->text('magicads_password')->nullable()->after('magicads_username');
            $table->text('magicads_api_key')->nullable()->after('magicads_password');
            $table->string('magicads_ai')->nullable()->after('magicads_api_key'); // ai parameter
            $table->string('magicads_ci')->nullable()->after('magicads_ai'); // ci parameter
            $table->string('magicads_gi')->nullable()->after('magicads_ci'); // gi parameter
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_api_credentials', function (Blueprint $table) {
            $table->dropColumn([
                'magicads_username',
                'magicads_password',
                'magicads_api_key',
                'magicads_ai',
                'magicads_ci',
                'magicads_gi'
            ]);
        });
    }
};
