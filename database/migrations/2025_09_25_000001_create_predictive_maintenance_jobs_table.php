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
        Schema::create('predictive_maintenance_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('job_id')->unique();
            $table->unsignedBigInteger('company_id');
            $table->enum('status', ['queued', 'processing', 'completed', 'failed'])->default('queued');
            $table->integer('progress')->default(0);
            $table->integer('total_assets')->default(0);
            $table->integer('predictions_generated')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            
            // Indexes for performance
            $table->index(['company_id', 'status']);
            $table->index('job_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('predictive_maintenance_jobs');
    }
};



