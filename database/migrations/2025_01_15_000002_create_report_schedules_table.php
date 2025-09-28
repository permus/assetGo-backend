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
        Schema::create('report_schedules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('template_id');
            $table->string('name'); // Human-readable schedule name
            $table->text('description')->nullable();
            $table->string('rrule'); // iCal RRULE format: FREQ=MONTHLY;BYHOUR=3
            $table->string('timezone')->default('UTC');
            $table->string('delivery_email')->nullable();
            $table->json('delivery_options')->nullable(); // Additional delivery settings
            $table->boolean('enabled')->default(false);
            $table->timestamp('last_run_at')->nullable();
            $table->timestamp('next_run_at')->nullable();
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('template_id')->references('id')->on('report_templates')->onDelete('cascade');
            
            // Indexes for performance
            $table->index(['company_id', 'enabled']);
            $table->index(['company_id', 'next_run_at']);
            $table->index(['enabled', 'next_run_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('report_schedules');
    }
};
