<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schedule_maintenance_assigned', function (Blueprint $table) {
            // Add unique constraint to prevent duplicate assignments
            // A user can only be assigned once per schedule
            $table->unique(['schedule_maintenance_id', 'team_id'], 'uniq_sma_schedule_user');
        });
    }

    public function down(): void
    {
        Schema::table('schedule_maintenance_assigned', function (Blueprint $table) {
            $table->dropUnique('uniq_sma_schedule_user');
        });
    }
};
