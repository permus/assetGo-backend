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
        Schema::create('report_runs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('template_id')->nullable();
            $table->string('report_key'); // e.g., assets.summary, maintenance.compliance
            $table->json('params')->nullable(); // Report parameters and filters
            $table->json('filters')->nullable(); // Applied filters for tracking
            $table->enum('format', ['pdf', 'xlsx', 'csv', 'json'])->default('json');
            $table->enum('status', ['queued', 'running', 'success', 'failed'])->default('queued');
            $table->unsignedBigInteger('row_count')->default(0);
            $table->string('file_path')->nullable(); // Storage path for exports
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->integer('execution_time_ms')->nullable(); // Performance tracking
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('template_id')->references('id')->on('report_templates')->onDelete('set null');
            
            // Indexes for performance
            $table->index(['company_id', 'report_key', 'status']);
            $table->index(['company_id', 'created_at']);
            $table->index(['company_id', 'user_id']);
            $table->index(['status', 'created_at']);
        });

        // Create view for report run statistics
        DB::statement('
            CREATE VIEW report_runs_summary AS
            SELECT
                company_id,
                report_key,
                COUNT(*) as total_runs,
                COUNT(CASE WHEN status = "success" THEN 1 END) as successful_runs,
                COUNT(CASE WHEN status = "failed" THEN 1 END) as failed_runs,
                AVG(execution_time_ms) as avg_execution_time_ms,
                MAX(created_at) as last_run_at,
                SUM(row_count) as total_rows_processed
            FROM report_runs
            GROUP BY company_id, report_key
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS report_runs_summary');
        Schema::dropIfExists('report_runs');
    }
};
