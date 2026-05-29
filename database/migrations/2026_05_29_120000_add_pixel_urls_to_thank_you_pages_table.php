<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('thank_you_pages', function (Blueprint $table) {
            if (!Schema::hasColumn('thank_you_pages', 'facebook_pixel_url')) {
                $table->text('facebook_pixel_url')->nullable()->after('v2_content');
            }
            if (!Schema::hasColumn('thank_you_pages', 'second_pixel_url')) {
                $table->text('second_pixel_url')->nullable()->after('facebook_pixel_url');
            }
        });
    }

    public function down(): void
    {
        Schema::table('thank_you_pages', function (Blueprint $table) {
            if (Schema::hasColumn('thank_you_pages', 'second_pixel_url')) {
                $table->dropColumn('second_pixel_url');
            }
            if (Schema::hasColumn('thank_you_pages', 'facebook_pixel_url')) {
                $table->dropColumn('facebook_pixel_url');
            }
        });
    }
};
