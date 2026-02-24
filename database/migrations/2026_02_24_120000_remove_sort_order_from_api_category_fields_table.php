<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Remove sort_order from api_category_fields; fields are ordered by id where required.
     */
    public function up(): void
    {
        Schema::table('api_category_fields', function (Blueprint $table) {
            $table->dropColumn('sort_order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('api_category_fields', function (Blueprint $table) {
            $table->integer('sort_order')->default(0)->after('encrypt');
        });
    }
};
