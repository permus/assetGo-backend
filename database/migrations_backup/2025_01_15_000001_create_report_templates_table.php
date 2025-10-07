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
        Schema::create('report_templates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('owner_id'); // User who created the template
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('report_key')->nullable(); // If based on standard report
            $table->json('definition'); // Report configuration: tables, fields, filters, group/order
            $table->json('default_filters')->nullable(); // Pre-configured filters
            $table->boolean('is_shared')->default(true); // Share within company
            $table->boolean('is_public')->default(false); // Company-wide visibility
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('owner_id')->references('id')->on('users')->onDelete('cascade');
            
            // Indexes for performance
            $table->index(['company_id', 'owner_id']);
            $table->index(['company_id', 'is_shared']);
            $table->index(['company_id', 'report_key']);
            $table->index(['company_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('report_templates');
    }
};
