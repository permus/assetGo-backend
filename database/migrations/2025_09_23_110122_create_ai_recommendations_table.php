<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ai_recommendations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->enum('rec_type', ['cost_optimization', 'maintenance', 'efficiency', 'compliance']);
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('impact', ['low', 'medium', 'high']);
            $table->enum('priority', ['low', 'medium', 'high']);
            $table->decimal('estimated_savings', 15, 2)->nullable();
            $table->decimal('implementation_cost', 15, 2)->nullable();
            $table->decimal('roi', 8, 2)->nullable();
            $table->string('payback_period')->nullable();
            $table->string('timeline');
            $table->json('actions');
            $table->decimal('confidence', 5, 2); // 0-100
            $table->boolean('implemented')->default(false);
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            
            // Indexes for performance
            $table->index(['company_id', 'rec_type']);
            $table->index(['company_id', 'priority']);
            $table->index(['company_id', 'impact']);
            $table->index(['company_id', 'implemented']);
            $table->index(['company_id', 'created_at']);
        });

        // Drop view if it exists (in case of failed migration)
        DB::statement('DROP VIEW IF EXISTS ai_recommendations_summary');
        
        // Create summary view
        DB::statement('
            CREATE VIEW ai_recommendations_summary AS
            SELECT
                company_id,
                COUNT(*) as total_recommendations,
                COUNT(CASE WHEN priority = "high" THEN 1 END) as high_priority_count,
                COALESCE(SUM(estimated_savings), 0) as total_savings,
                COALESCE(SUM(implementation_cost), 0) as total_cost,
                CASE
                    WHEN COALESCE(SUM(implementation_cost), 0) = 0 THEN 0
                    ELSE ((COALESCE(SUM(estimated_savings), 0) - COALESCE(SUM(implementation_cost), 0))
                          / NULLIF(SUM(implementation_cost), 0)) * 100
                END as roi,
                MAX(created_at) as last_updated
            FROM ai_recommendations
            GROUP BY company_id
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS ai_recommendations_summary');
        Schema::dropIfExists('ai_recommendations');
    }
};