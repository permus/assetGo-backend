<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('inventory_parts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('part_number')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->text('manufacturer')->nullable();
            $table->text('maintenance_category')->nullable();
            $table->string('uom')->default('each');
            $table->decimal('unit_cost', 12, 2)->default(0);
            $table->json('specifications')->nullable();
            $table->json('compatible_assets')->nullable();
            $table->unsignedBigInteger('category_id')->nullable();
            $table->integer('reorder_point')->default(0);
            $table->integer('reorder_qty')->default(0);
            $table->integer('minimum_stock')->nullable();
            $table->integer('maximum_stock')->nullable();
            $table->boolean('is_consumable')->nullable();
            $table->boolean('usage_tracking')->nullable();
            $table->unsignedBigInteger('preferred_supplier_id')->nullable();
            $table->string('barcode')->nullable();
            $table->string('image_path')->nullable();
            $table->string('status')->default('active'); // active|archived
            $table->boolean('is_archived')->default(false);
            $table->char('abc_class', 1)->nullable(); // A|B|C
            $table->json('extra')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['company_id', 'part_number']);
            $table->index('preferred_supplier_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_parts');
    }
};


