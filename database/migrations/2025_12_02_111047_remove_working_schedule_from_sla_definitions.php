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
        Schema::table('sla_definitions', function (Blueprint $table) {
            if (Schema::hasColumn('sla_definitions', 'business_hours_only')) {
                $table->dropColumn('business_hours_only');
            }
            if (Schema::hasColumn('sla_definitions', 'working_days')) {
                $table->dropColumn('working_days');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sla_definitions', function (Blueprint $table) {
            $table->boolean('business_hours_only')->default(false)->after('completion_time_hours');
            $table->json('working_days')->after('business_hours_only');
        });
    }
};
