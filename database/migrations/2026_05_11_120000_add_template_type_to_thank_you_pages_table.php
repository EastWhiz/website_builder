<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('thank_you_pages', function (Blueprint $table) {
            if (!Schema::hasColumn('thank_you_pages', 'template_type')) {
                $table->string('template_type', 50)
                    ->default('legacy')
                    ->after('hero_background_color');
            }
        });
    }

    public function down(): void
    {
        Schema::table('thank_you_pages', function (Blueprint $table) {
            if (Schema::hasColumn('thank_you_pages', 'template_type')) {
                $table->dropColumn('template_type');
            }
        });
    }
};
