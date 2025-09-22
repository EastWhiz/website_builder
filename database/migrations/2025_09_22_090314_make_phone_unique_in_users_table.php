<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // First, update any null phone numbers with temporary unique values
        DB::statement("UPDATE users SET phone = CONCAT('temp_', id, '_', UNIX_TIMESTAMP()) WHERE phone IS NULL OR phone = ''");

        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 20)->nullable(false)->unique()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['phone']);
            $table->string('phone', 20)->nullable()->change();
        });
    }
};
