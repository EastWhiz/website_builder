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
            // Add NewMedis API credentials
            $table->string('newmedis_username')->nullable()->after('riceleads_api_key');
            $table->text('newmedis_password')->nullable()->after('newmedis_username');
            $table->text('newmedis_api_key')->nullable()->after('newmedis_password');
            $table->string('newmedis_ai')->nullable()->after('newmedis_api_key'); // ai parameter
            $table->string('newmedis_ci')->nullable()->after('newmedis_ai'); // ci parameter
            $table->string('newmedis_gi')->nullable()->after('newmedis_ci'); // gi parameter
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_api_credentials', function (Blueprint $table) {
            $table->dropColumn([
                'newmedis_username',
                'newmedis_password',
                'newmedis_api_key',
                'newmedis_ai',
                'newmedis_ci',
                'newmedis_gi'
            ]);
        });
    }
};

