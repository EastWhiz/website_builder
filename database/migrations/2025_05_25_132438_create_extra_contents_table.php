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
        Schema::create('extra_contents', function (Blueprint $table) {
            $table->id();
            $table->uuid('angle_template_uuid');
            $table->enum('type', ['image']);
            $table->string('name');
            $table->string('blob_url')->nullable();
            $table->boolean('can_be_deleted')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('extra_contents');
    }
};
