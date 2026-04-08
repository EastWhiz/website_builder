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
        Schema::table('roles', function (Blueprint $table) {
            $table->string('scope', 30)->default('platform')->after('id');
            $table->string('key', 80)->nullable()->after('name');
            $table->string('description')->nullable()->after('key');
            $table->boolean('is_system')->default(false)->after('description');
            $table->boolean('is_active')->default(true)->after('is_system');
            $table->unsignedBigInteger('created_by')->nullable()->after('is_active');

            $table->index('scope');
            $table->index('key');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropIndex(['scope']);
            $table->dropIndex(['key']);
            $table->dropIndex(['is_active']);

            $table->dropColumn([
                'scope',
                'key',
                'description',
                'is_system',
                'is_active',
                'created_by',
            ]);
        });
    }
};

