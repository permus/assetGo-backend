<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Check if category_id column already exists
        if (!Schema::hasColumn('sla_definitions', 'category_id')) {
            Schema::table('sla_definitions', function (Blueprint $table) {
                // Add new category_id column
                $table->unsignedBigInteger('category_id')->nullable()->after('priority_level');
                
                // Add foreign key constraint
                $table->foreign('category_id')->references('id')->on('work_order_categories')->onDelete('set null');
                
                // Add index
                $table->index('category_id');
            });
        }

        // Migrate existing category string values to category_id if possible
        // This attempts to match category names to work_order_categories
        if (Schema::hasColumn('sla_definitions', 'category')) {
            DB::statement("
                UPDATE sla_definitions sd
                INNER JOIN work_order_categories woc ON sd.category = woc.name
                SET sd.category_id = woc.id
                WHERE sd.category IS NOT NULL AND sd.category != ''
            ");

            // Drop the old category column
            Schema::table('sla_definitions', function (Blueprint $table) {
                $table->dropColumn('category');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sla_definitions', function (Blueprint $table) {
            // Add back category column
            $table->string('category')->nullable()->after('priority_level');
        });

        // Migrate category_id back to category string
        DB::statement("
            UPDATE sla_definitions sd
            INNER JOIN work_order_categories woc ON sd.category_id = woc.id
            SET sd.category = woc.name
            WHERE sd.category_id IS NOT NULL
        ");

        Schema::table('sla_definitions', function (Blueprint $table) {
            // Drop foreign key and index
            $table->dropForeign(['category_id']);
            $table->dropIndex(['category_id']);
            
            // Drop category_id column
            $table->dropColumn('category_id');
        });
    }
};
