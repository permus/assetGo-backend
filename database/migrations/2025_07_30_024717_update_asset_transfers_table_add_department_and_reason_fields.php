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
        Schema::table('asset_transfers', function (Blueprint $table) {
            // Add department fields
            $table->unsignedBigInteger('old_department_id')->nullable()->after('from_user_id');
            $table->unsignedBigInteger('new_department_id')->nullable()->after('to_user_id');
            
            // Add reason field
            $table->string('reason')->after('new_department_id');
            
            // Add created_by field
            $table->unsignedBigInteger('created_by')->after('notes');
            
            // Rename existing fields to match requirements
            $table->renameColumn('from_location_id', 'old_location_id');
            $table->renameColumn('to_location_id', 'new_location_id');
            
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('asset_transfers', function (Blueprint $table) {
            // Drop foreign key constraints
            $table->dropForeign(['old_department_id']);
            $table->dropForeign(['new_department_id']);
            $table->dropForeign(['created_by']);
            
            // Drop columns
            $table->dropColumn(['old_department_id', 'new_department_id', 'reason', 'created_by']);
            
            // Rename columns back
            $table->renameColumn('old_location_id', 'from_location_id');
            $table->renameColumn('new_location_id', 'to_location_id');
        });
    }
};
