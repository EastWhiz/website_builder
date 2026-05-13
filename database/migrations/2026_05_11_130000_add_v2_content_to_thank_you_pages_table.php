<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('thank_you_pages', function (Blueprint $table) {
            if (!Schema::hasColumn('thank_you_pages', 'v2_content')) {
                $table->json('v2_content')->nullable()->after('template_type');
            }
        });
    }

    public function down(): void
    {
        Schema::table('thank_you_pages', function (Blueprint $table) {
            if (Schema::hasColumn('thank_you_pages', 'v2_content')) {
                $table->dropColumn('v2_content');
            }
        });
    }
};
