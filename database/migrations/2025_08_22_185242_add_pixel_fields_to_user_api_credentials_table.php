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
            // Pixel Management fields
            $table->text('facebook_pixel_url')->nullable()->after('tigloo_gi');
            $table->text('second_pixel_url')->nullable()->after('facebook_pixel_url');
        });
    }

    public function down(): void
    {
        Schema::table('user_api_credentials', function (Blueprint $table) {
            $table->dropColumn([
                'facebook_pixel_url',
                'second_pixel_url'
            ]);
        });
    }
};
