<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('inventory_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('part_id');
            $table->unsignedBigInteger('location_id');
            $table->enum('type', ['receipt','issue','adjustment','transfer_out','transfer_in','return']);
            $table->integer('quantity');
            $table->decimal('unit_cost', 12, 2)->nullable();
            $table->decimal('total_cost', 12, 2)->nullable();
            $table->string('reason')->nullable();
            $table->text('notes')->nullable();
            $table->string('reference')->nullable();
            $table->unsignedBigInteger('related_id')->nullable(); // PO, WO, transfer id, etc.
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();
            $table->index(['company_id','part_id','location_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_transactions');
    }
};


