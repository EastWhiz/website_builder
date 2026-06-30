<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('turnstile_widgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('hostname')->nullable();
            $table->string('cloudflare_widget_id')->nullable();
            $table->string('site_key');
            $table->text('secret_key_encrypted');
            $table->string('mode', 30)->default('managed');
            $table->string('widget_scope', 30)->default('shared');
            $table->json('domains_json')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->index('organization_id');
            $table->index('hostname');
            $table->index('site_key');
            $table->index('widget_scope');
            $table->unique(['organization_id', 'hostname'], 'turnstile_widgets_org_hostname_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('turnstile_widgets');
    }
};
