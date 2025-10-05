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
            if (!Schema::hasColumn('work_orders', 'status_id')) {
                $table->unsignedBigInteger('status_id')->nullable()->after('company_id');
            }
            if (!Schema::hasColumn('work_orders', 'priority_id')) {
                $table->unsignedBigInteger('priority_id')->nullable()->after('status_id');
            }
            if (!Schema::hasColumn('work_orders', 'category_id')) {
                $table->unsignedBigInteger('category_id')->nullable()->after('priority_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            $table->dropColumn(['status_id', 'priority_id', 'category_id']);
        });
    }
};
