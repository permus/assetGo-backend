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
            $table->unsignedBigInteger('company_id');       // Company ownership
            $table->unsignedBigInteger('user_id');       // User who created this location
            $table->unsignedBigInteger('location_type_id'); // Type (from location_types)
            $table->unsignedBigInteger('parent_id')->nullable(); // For hierarchy
            $table->string('name');
            $table->string('slug')->unique()->nullable();   // SEO & QR-friendly
            $table->string('address')->nullable();          // From Google Maps
            $table->text('description')->nullable();
            $table->string('qr_code_path')->nullable();     // Path to generated QR code
            $table->unsignedInteger('hierarchy_level')->default(0); // Cached level
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('location_type_id')->references('id')->on('location_types')->onDelete('cascade');
            $table->foreign('parent_id')->references('id')->on('locations')->onDelete('cascade');
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
