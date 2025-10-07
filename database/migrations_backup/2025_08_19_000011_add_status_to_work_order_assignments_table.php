<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Add status column if it doesn't exist
        if (!Schema::hasColumn('work_order_assignments', 'status')) {
            Schema::table('work_order_assignments', function (Blueprint $table) {
                $table->string('status')->default('assigned')->after('assigned_by');
            });

            // Backfill existing rows
            DB::table('work_order_assignments')->whereNull('status')->update(['status' => 'assigned']);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('work_order_assignments', 'status')) {
            Schema::table('work_order_assignments', function (Blueprint $table) {
                $table->dropColumn('status');
            });
        }
    }
};


