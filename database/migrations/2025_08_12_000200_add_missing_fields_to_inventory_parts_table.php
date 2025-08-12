<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('inventory_parts', function (Blueprint $table) {
            $table->text('manufacturer')->nullable()->after('description');
            $table->text('maintenance_category')->nullable()->after('manufacturer');
            $table->json('specifications')->nullable()->after('unit_cost');
            $table->json('compatible_assets')->nullable()->after('specifications');
            $table->integer('minimum_stock')->nullable()->after('reorder_qty');
            $table->integer('maximum_stock')->nullable()->after('minimum_stock');
            $table->boolean('is_consumable')->nullable()->after('maximum_stock');
            $table->boolean('usage_tracking')->nullable()->after('is_consumable');
            $table->unsignedBigInteger('preferred_supplier_id')->nullable()->after('usage_tracking');
            $table->index('preferred_supplier_id');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_parts', function (Blueprint $table) {
            $table->dropIndex(['preferred_supplier_id']);
            $table->dropColumn([
                'manufacturer',
                'maintenance_category',
                'specifications',
                'compatible_assets',
                'minimum_stock',
                'maximum_stock',
                'is_consumable',
                'usage_tracking',
                'preferred_supplier_id',
            ]);
        });
    }
};


