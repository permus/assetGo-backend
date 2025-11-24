<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schedule_maintenance', function (Blueprint $table) {
            $table->unsignedBigInteger('assigned_user_id')->nullable()->after('priority_id');
            $table->unsignedBigInteger('assigned_role_id')->nullable()->after('assigned_user_id');
            $table->unsignedBigInteger('assigned_team_id')->nullable()->after('assigned_role_id');
            $table->json('auto_generated_wo_ids')->nullable()->after('assigned_team_id');

            $table->foreign('assigned_user_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('schedule_maintenance', function (Blueprint $table) {
            $table->dropForeign(['assigned_user_id']);
            $table->dropColumn(['assigned_user_id', 'assigned_role_id', 'assigned_team_id', 'auto_generated_wo_ids']);
        });
    }
};

