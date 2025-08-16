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
        Schema::create('user_api_credentials', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');

            // AWeber API credentials
            $table->text('aweber_client_id')->nullable();
            $table->text('aweber_client_secret')->nullable();
            $table->string('aweber_account_id')->nullable();
            $table->string('aweber_list_id')->nullable();

            // Dark API credentials
            $table->string('dark_username')->nullable();
            $table->text('dark_password')->nullable();
            $table->text('dark_api_key')->nullable();

            // ELPS API credentials
            $table->string('elps_username')->nullable();
            $table->text('elps_password')->nullable();
            $table->text('elps_api_key')->nullable();

            // MeeseeksMedia API credentials
            $table->text('meeseeks_api_key')->nullable();

            // Novelix API credentials
            $table->text('novelix_api_key')->nullable();

            // Tigloo API credentials
            $table->string('tigloo_username')->nullable();
            $table->text('tigloo_password')->nullable();
            $table->text('tigloo_api_key')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_api_credentials');
    }
};
