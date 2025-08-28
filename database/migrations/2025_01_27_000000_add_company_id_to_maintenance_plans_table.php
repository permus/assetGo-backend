<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenance_plans', function (Blueprint $table) {
            // Add company_id column
            $table->unsignedBigInteger('company_id')->after('id');
            
            // Drop the old unique constraint on name
            $table->dropUnique(['name']);
            
            // Add new composite unique constraint
            $table->unique(['company_id', 'name']);
            
            // Add index on company_id
            $table->index('company_id');
        });
        
        // Set company_id for existing records (assuming they belong to company_id = 1)
        // You may need to adjust this based on your existing data
        DB::statement('UPDATE maintenance_plans SET company_id = 1 WHERE company_id IS NULL');
        
        // Make company_id not nullable after setting default values
        Schema::table('maintenance_plans', function (Blueprint $table) {
            $table->unsignedBigInteger('company_id')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_plans', function (Blueprint $table) {
            // Drop the new composite unique constraint
            $table->dropUnique(['company_id', 'name']);
            
            // Drop company_id index
            $table->dropIndex(['company_id']);
            
            // Drop company_id column
            $table->dropColumn('company_id');
            
            // Restore the old unique constraint on name
            $table->string('name')->unique();
        });
    }
};
