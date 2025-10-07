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
        Schema::create('ai_analytics_runs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->json('payload'); // Full AnalyticsData JSON
            $table->decimal('health_score', 5, 2); // 0-100, denormalized for quick sort/filter
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            
            // Indexes for performance
            $table->index(['company_id', 'created_at']);
            $table->index(['company_id', 'health_score']);
        });

        Schema::create('ai_analytics_schedule', function (Blueprint $table) {
            $table->unsignedBigInteger('company_id')->primary();
            $table->boolean('enabled')->default(false);
            $table->enum('frequency', ['daily', 'weekly', 'monthly'])->default('weekly');
            $table->integer('hour_utc')->default(3); // 0-23
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
        });

        // Drop view if it exists (in case of failed migration)
        DB::statement('DROP VIEW IF EXISTS ai_analytics_latest');
        
        // Create view for latest snapshot (MySQL compatible)
        DB::statement('
            CREATE VIEW ai_analytics_latest AS
            SELECT 
                a1.company_id, 
                a1.id, 
                a1.payload, 
                a1.health_score, 
                a1.created_at
            FROM ai_analytics_runs a1
            INNER JOIN (
                SELECT company_id, MAX(created_at) as max_created_at
                FROM ai_analytics_runs
                GROUP BY company_id
            ) a2 ON a1.company_id = a2.company_id AND a1.created_at = a2.max_created_at
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS ai_analytics_latest');
        Schema::dropIfExists('ai_analytics_schedule');
        Schema::dropIfExists('ai_analytics_runs');
    }
};