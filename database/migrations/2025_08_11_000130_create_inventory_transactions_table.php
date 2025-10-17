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
            $table->unsignedBigInteger('from_location_id')->nullable();
            $table->unsignedBigInteger('to_location_id')->nullable();
            $table->enum('type', ['receipt','issue','adjustment','transfer_out','transfer_in','return']);
            $table->integer('quantity');
            $table->decimal('unit_cost', 12, 2)->nullable();
            $table->decimal('total_cost', 12, 2)->nullable();
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('reason')->nullable();
            $table->text('notes')->nullable();
            $table->string('reference')->nullable();
            $table->unsignedBigInteger('related_id')->nullable(); // PO, WO, transfer id, etc.
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();
            $table->index(['company_id','part_id','location_id']);
            $table->index(['from_location_id','to_location_id']);
            $table->index(['reference_type','reference_id']);
            $table->index(['company_id', 'type', 'created_at'], 'inv_tx_company_type_created_idx');
            $table->index(['company_id', 'part_id', 'created_at'], 'inv_tx_company_part_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_transactions');
    }
};


