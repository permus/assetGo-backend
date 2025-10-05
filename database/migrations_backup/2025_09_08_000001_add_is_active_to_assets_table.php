<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            if (!Schema::hasColumn('assets', 'is_active')) {
                $table->unsignedTinyInteger('is_active')
                    ->default(1)
                    ->after('status')
                    ->comment('1 = active, 2 = archived');
            }
        });

        // Backfill existing rows based on soft delete state
        try {
            DB::table('assets')->whereNotNull('deleted_at')->update(['is_active' => 2]);
            DB::table('assets')->whereNull('deleted_at')->update(['is_active' => 1]);
        } catch (\Throwable $e) {
            // Ignore backfill errors; column will still exist
        }
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            if (Schema::hasColumn('assets', 'is_active')) {
                $table->dropColumn('is_active');
            }
        });
    }
};


