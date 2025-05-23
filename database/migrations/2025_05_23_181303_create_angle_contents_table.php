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
        Schema::create('angle_contents', function (Blueprint $table) {
            $table->id();
            $table->uuid('angle_uuid');
            $table->enum('type', ['css', 'js', 'html', 'font', 'image']);
            $table->string('name');
            $table->longText('content')->nullable();
            $table->unsignedBigInteger('sort')->nullable();
            $table->boolean('can_be_deleted')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('angle_contents');
    }
};
