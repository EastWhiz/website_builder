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
            // Add Seamediaone API credentials
            $table->string('seamediaone_username')->nullable()->after('newmedis_gi');
            $table->text('seamediaone_password')->nullable()->after('seamediaone_username');
            $table->text('seamediaone_api_key')->nullable()->after('seamediaone_password');
            $table->string('seamediaone_ai')->nullable()->after('seamediaone_api_key'); // ai parameter
            $table->string('seamediaone_ci')->nullable()->after('seamediaone_ai'); // ci parameter
            $table->string('seamediaone_gi')->nullable()->after('seamediaone_ci'); // gi parameter
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_api_credentials', function (Blueprint $table) {
            $table->dropColumn([
                'seamediaone_username',
                'seamediaone_password',
                'seamediaone_api_key',
                'seamediaone_ai',
                'seamediaone_ci',
                'seamediaone_gi'
            ]);
        });
    }
};

