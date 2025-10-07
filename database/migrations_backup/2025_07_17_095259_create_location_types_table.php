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
        Schema::create('location_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');                     // e.g., "Campus", "Building", "Room"
            $table->string('category')->nullable();     // e.g., "Residential", "Commercial", etc.
            $table->unsignedTinyInteger('hierarchy_level'); // 0 = top-level, up to 3
            $table->string('icon')->nullable(); // Optional for UI representation
            $table->json('suggestions')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('location_types');
    }
};
