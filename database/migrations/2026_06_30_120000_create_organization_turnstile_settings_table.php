<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_turnstile_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('enabled')->default(false);
            $table->boolean('auto_provision_enabled')->default(false);
            $table->string('cloudflare_account_id')->nullable();
            $table->text('cloudflare_api_token_encrypted')->nullable();
            $table->string('default_widget_mode', 30)->default('managed');
            $table->string('widget_scope', 30)->default('shared');
            $table->timestamps();

            $table->index('enabled');
            $table->index('auto_provision_enabled');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_turnstile_settings');
    }
};
