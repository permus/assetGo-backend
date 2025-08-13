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
        Schema::table('purchase_order_items', function (Blueprint $table) {
            // Drop the existing part_id column
            $table->dropColumn('part_id');
        });

        Schema::table('purchase_order_items', function (Blueprint $table) {
            // Recreate part_id as nullable
            $table->unsignedBigInteger('part_id')->nullable()->after('purchase_order_id');
            
            // Add foreign key constraint
            $table->foreign('part_id')->references('id')->on('inventory_parts')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_order_items', function (Blueprint $table) {
            // Remove foreign key constraint
            $table->dropForeign(['part_id']);
            
            // Drop the nullable part_id column
            $table->dropColumn('part_id');
        });

        Schema::table('purchase_order_items', function (Blueprint $table) {
            // Recreate part_id as not nullable (original state)
            $table->unsignedBigInteger('part_id')->after('purchase_order_id');
        });
    }
};
