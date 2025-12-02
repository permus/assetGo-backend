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
        Schema::create('work_order_sla_violations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('work_order_id');
            $table->unsignedBigInteger('sla_definition_id');
            $table->enum('violation_type', ['response_time', 'containment_time', 'completion_time']);
            $table->timestamp('violated_at');
            $table->timestamp('notified_at')->nullable();
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('work_order_id')->references('id')->on('work_orders')->onDelete('cascade');
            $table->foreign('sla_definition_id')->references('id')->on('sla_definitions')->onDelete('cascade');

            // Indexes
            $table->index('work_order_id');
            $table->index('sla_definition_id');
            $table->index('violated_at');
            $table->index(['work_order_id', 'violation_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('work_order_sla_violations');
    }
};
