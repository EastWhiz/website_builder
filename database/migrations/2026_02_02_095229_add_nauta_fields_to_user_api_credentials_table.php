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
            // Add Nauta API credentials
            $table->text('nauta_api_token')->nullable()->after('seamediaone_gi');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_api_credentials', function (Blueprint $table) {
            $table->dropColumn([
                'nauta_api_token'
            ]);
        });
    }
};
