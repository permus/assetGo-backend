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
        Schema::create('location_asset_summary', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('location_id');
            $table->unsignedBigInteger('company_id')->nullable(); // If private template
            $table->unsignedBigInteger('user_id')->nullable();       // User who created this location
            $table->unsignedInteger('asset_count')->default(0);
            $table->decimal('health_score', 5, 2)->default(100.00);

            $table->foreign('location_id')->references('id')->on('locations')->onDelete('cascade');
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('location_asset_summary');
    }
};
