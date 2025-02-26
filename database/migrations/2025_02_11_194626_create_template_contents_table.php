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
        Schema::create('template_contents', function (Blueprint $table) {
            $table->id();
            $table->uuid('template_uuid');
            $table->enum('type', ['css', 'js', 'html', 'font', 'image']);
            $table->string('name');
            $table->longText('content')->nullable();
            $table->unsignedBigInteger('sort')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('template_contents');
    }
};
