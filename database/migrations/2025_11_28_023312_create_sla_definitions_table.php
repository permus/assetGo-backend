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
        Schema::create('sla_definitions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->enum('applies_to', ['work_orders', 'maintenance', 'both']);
            $table->enum('priority_level', ['low', 'medium', 'high', 'critical', 'ppm'])->nullable();
            $table->string('category')->nullable();
            $table->decimal('response_time_hours', 8, 2);
            $table->decimal('containment_time_hours', 8, 2)->nullable();
            $table->decimal('completion_time_hours', 8, 2);
            $table->boolean('business_hours_only')->default(false);
            $table->json('working_days');
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by');
            $table->timestamps();
            $table->softDeletes();

            // Foreign key constraints
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('restrict');

            // Indexes
            $table->index('company_id');
            $table->index('applies_to');
            $table->index('is_active');
            $table->index(['company_id', 'applies_to', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sla_definitions');
    }
};
