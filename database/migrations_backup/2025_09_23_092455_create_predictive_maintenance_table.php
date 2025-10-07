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
        Schema::create('predictive_maintenance', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('asset_id')->nullable();
            $table->string('risk_level')->check('risk_level in ("high","medium","low")');
            $table->date('predicted_failure_date')->nullable();
            $table->decimal('confidence', 5, 2)->default(0); // 0-100
            $table->text('recommended_action');
            $table->decimal('estimated_cost', 10, 2)->default(0);
            $table->decimal('preventive_cost', 10, 2)->default(0);
            $table->decimal('savings', 10, 2)->default(0);
            $table->json('factors')->nullable(); // Array of risk factors
            $table->json('timeline')->nullable(); // Immediate, short-term, long-term actions
            $table->unsignedBigInteger('company_id');
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('asset_id')->references('id')->on('assets')->onDelete('set null');
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            
            // Indexes for performance
            $table->index(['company_id', 'risk_level']);
            $table->index(['company_id', 'predicted_failure_date']);
            $table->index(['company_id', 'created_at']);
        });

        // Create materialized summary view
        DB::statement('
            CREATE VIEW predictive_maintenance_summary AS
            SELECT
                company_id,
                COUNT(*) as total_predictions,
                COUNT(CASE WHEN risk_level = "high" THEN 1 END) as high_risk_count,
                COALESCE(SUM(savings), 0) as total_savings,
                COALESCE(AVG(confidence), 0) as avg_confidence,
                MAX(created_at) as last_updated
            FROM predictive_maintenance
            GROUP BY company_id
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS predictive_maintenance_summary');
        Schema::dropIfExists('predictive_maintenance');
    }
};