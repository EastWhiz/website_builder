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
        Schema::create('user_api_instance_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_api_instance_id')->constrained('user_api_instances')->onDelete('cascade');
            $table->foreignId('api_category_field_id')->constrained('api_category_fields')->onDelete('cascade');
            $table->text('value')->nullable();
            $table->timestamps();
            
            $table->unique(['user_api_instance_id', 'api_category_field_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_api_instance_values');
    }
};
