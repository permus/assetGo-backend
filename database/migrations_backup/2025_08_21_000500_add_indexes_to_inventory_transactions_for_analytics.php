<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_transactions', function (Blueprint $table) {
            $table->index(['company_id', 'type', 'created_at'], 'inv_tx_company_type_created_idx');
            $table->index(['company_id', 'part_id', 'created_at'], 'inv_tx_company_part_created_idx');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_transactions', function (Blueprint $table) {
            $table->dropIndex('inv_tx_company_type_created_idx');
            $table->dropIndex('inv_tx_company_part_created_idx');
        });
    }
};


