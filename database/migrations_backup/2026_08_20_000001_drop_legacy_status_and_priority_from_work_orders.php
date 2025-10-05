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
        Schema::table('work_orders', function (Blueprint $table) {
            if (Schema::hasColumn('work_orders', 'status')) {
                $table->dropColumn('status');
            }
            if (Schema::hasColumn('work_orders', 'priority')) {
                $table->dropColumn('priority');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            if (!Schema::hasColumn('work_orders', 'priority')) {
                $table->enum('priority', ['low', 'medium', 'high', 'critical'])->default('medium');
            }
            if (!Schema::hasColumn('work_orders', 'status')) {
                $table->enum('status', ['open', 'in_progress', 'completed', 'on_hold', 'cancelled'])->default('open');
            }
        });
    }
};


