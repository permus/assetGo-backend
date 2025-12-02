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
        Schema::table('asset_inventory_part', function (Blueprint $table) {
            if (!Schema::hasColumn('asset_inventory_part', 'qty')) {
                $table->decimal('qty', 10, 3)->default(1)->after('inventory_part_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('asset_inventory_part', function (Blueprint $table) {
            if (Schema::hasColumn('asset_inventory_part', 'qty')) {
                $table->dropColumn('qty');
            }
        });
    }
};
