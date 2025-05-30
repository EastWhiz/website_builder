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
        Schema::create('angle_templates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('angle_id');
            $table->unsignedBigInteger('template_id');
            $table->unsignedBigInteger('user_id');
            $table->string('name');
            $table->longText('main_html');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('angle_templates');
    }
};
