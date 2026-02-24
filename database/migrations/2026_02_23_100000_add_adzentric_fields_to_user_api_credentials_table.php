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
            $table->string('adzentric_affid')->nullable()->after('magicads_gi');
            $table->text('adzentric_api_key')->nullable()->after('adzentric_affid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_api_credentials', function (Blueprint $table) {
            $table->dropColumn(['adzentric_affid', 'adzentric_api_key']);
        });
    }
};
