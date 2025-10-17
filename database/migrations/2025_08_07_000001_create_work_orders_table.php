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
        Schema::create('work_orders', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->dateTime('due_date')->nullable();
            $table->dateTime('completed_at')->nullable();
            
            // Optional relationships
            $table->unsignedBigInteger('asset_id')->nullable();
            $table->unsignedBigInteger('location_id')->nullable();
            $table->unsignedBigInteger('assigned_to')->nullable(); // User ID
            $table->unsignedBigInteger('assigned_by')->nullable(); // User ID who assigned
            $table->unsignedBigInteger('created_by'); // User ID who created
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('status_id')->nullable();
            $table->unsignedBigInteger('priority_id')->nullable();
            $table->unsignedBigInteger('category_id')->nullable();
            
            // Additional fields
            $table->decimal('estimated_hours', 8, 2)->nullable();
            $table->decimal('actual_hours', 8, 2)->nullable();
            $table->text('notes')->nullable();
            $table->json('meta')->nullable(); // For extensibility
            
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes for performance
            $table->index(['company_id', 'status_id']);
            $table->index(['company_id', 'priority_id']);
            $table->index(['company_id', 'assigned_to']);
            $table->index(['company_id', 'due_date']);
            $table->index(['company_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('work_orders');
    }
};
