<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Step 1.4: soft-delete columns per soft-delete-cascade-matrix-v1.txt
     * (user_api_instances already has deleted_at from 2026_02_27_100000).
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('angles', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('angle_templates', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('thank_you_pages', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('user_api_instance_values', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('otp_service_credentials', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('user_api_credentials', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_api_credentials', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('otp_service_credentials', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('user_api_instance_values', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('thank_you_pages', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('angle_templates', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('angles', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
