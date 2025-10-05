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
        Schema::create('asset_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('color')->default('#6B7280'); // Color for the status dot
            $table->text('description')->nullable(); // Description like "Asset is operational and in use"
            $table->boolean('is_active')->default(true); // Whether this status is available for selection
            $table->integer('sort_order')->default(0); // For ordering in dropdown
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asset_statuses');
    }
};
