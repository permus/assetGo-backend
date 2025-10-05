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
        Schema::create('asset_import_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('job_id')->unique(); // UUID for public reference
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('company_id');
            $table->string('status')->default('pending'); // pending, processing, completed, failed
            $table->integer('total_assets')->default(0);
            $table->integer('processed_assets')->default(0);
            $table->integer('successful_imports')->default(0);
            $table->integer('failed_imports')->default(0);
            $table->json('import_data'); // Store the assets data to be imported
            $table->json('errors')->nullable(); // Store any errors that occurred
            $table->json('imported_assets')->nullable(); // Store successfully imported asset IDs
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->index(['job_id', 'status']);
            $table->index(['user_id', 'company_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asset_import_jobs');
    }
};