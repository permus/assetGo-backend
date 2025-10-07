<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_order_parts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('work_order_id');
            $table->unsignedBigInteger('part_id');
            $table->unsignedBigInteger('location_id')->nullable();
            $table->decimal('qty', 12, 3);
            $table->decimal('unit_cost', 12, 2)->nullable();
            $table->string('status')->default('reserved'); // reserved | consumed
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->foreign('work_order_id')->references('id')->on('work_orders')->onDelete('cascade');
            $table->foreign('part_id')->references('id')->on('inventory_parts')->onDelete('restrict');
            $table->index(['work_order_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_order_parts');
    }
};


