<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Phase 2.5: activity logging for organization lifecycle.
     */
    public function up(): void
    {
        Schema::create('organization_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id')->nullable();
            $table->unsignedBigInteger('actor_user_id')->nullable();
            $table->string('action', 80);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('organization_id');
            $table->index('actor_user_id');
            $table->index('action');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organization_activity_logs');
    }
};

