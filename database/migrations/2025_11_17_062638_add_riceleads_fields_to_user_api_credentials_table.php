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
            // Add Riceleads API credentials
            $table->string('riceleads_affid')->nullable()->after('pastile_gi');
            $table->text('riceleads_api_key')->nullable()->after('riceleads_affid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_api_credentials', function (Blueprint $table) {
            $table->dropColumn([
                'riceleads_affid',
                'riceleads_api_key'
            ]);
        });
    }
};

