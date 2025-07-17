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
        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');                // Company ownership
            $table->unsignedBigInteger('parent_id')->nullable();     // For hierarchy (self-referencing)
            $table->unsignedBigInteger('location_type_id');          // Location type
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('address')->nullable();
            $table->string('slug')->nullable()->unique();
            $table->string('qr_code_path')->nullable();                 // ✅ QR Code image path
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('locations');
    }
};
