<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_plan_parts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('maintenance_plan_id');
            $table->unsignedBigInteger('part_id');
            $table->decimal('default_qty', 12, 3)->nullable();
            $table->boolean('is_required')->default(true);
            $table->timestamps();

            $table->foreign('maintenance_plan_id')
                  ->references('id')
                  ->on('maintenance_plans')
                  ->onDelete('cascade');

            $table->foreign('part_id')
                  ->references('id')
                  ->on('inventory_parts')
                  ->onDelete('cascade');

            $table->unique(['maintenance_plan_id', 'part_id'], 'plan_part_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_plan_parts');
    }
};

