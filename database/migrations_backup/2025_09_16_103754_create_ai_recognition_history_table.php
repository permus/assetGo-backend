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
        Schema::create('ai_recognition_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('company_id')->constrained('companies');
            $table->json('image_paths');              // you may store S3/Supabase paths, or data URL hashes
            $table->json('recognition_result');       // normalized RecognitionResult JSON
            $table->decimal('confidence_score', 5, 2);
            $table->enum('feedback_type', ['positive','negative','correction'])->nullable();
            $table->json('feedback_data')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_recognition_history');
    }
};
