<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_mail_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('smtp_host', 255);
            $table->unsignedSmallInteger('smtp_port');
            $table->string('smtp_encryption', 20)->nullable();
            $table->string('smtp_username');
            $table->text('smtp_password');
            $table->string('mail_from_address');
            $table->string('mail_from_name')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_mail_settings');
    }
};
