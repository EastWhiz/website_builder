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
        Schema::table('template_contents', function (Blueprint $table) {
            $table->unsignedBigInteger('sort')->nullable()->after('content');
            $table->boolean('can_be_deleted')->default(true)->after('sort');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('template_contents', function (Blueprint $table) {
            //
        });
    }
};
