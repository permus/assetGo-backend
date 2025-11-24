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
        Schema::create('asset_inventory_part', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('asset_id');
            $table->unsignedBigInteger('inventory_part_id');
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('asset_id')
                  ->references('id')
                  ->on('assets')
                  ->onDelete('cascade');

            $table->foreign('inventory_part_id')
                  ->references('id')
                  ->on('inventory_parts')
                  ->onDelete('cascade');

            // Composite unique index to prevent duplicate relationships
            $table->unique(['asset_id', 'inventory_part_id'], 'asset_part_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asset_inventory_part');
    }
};

