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
        Schema::create('ai_training_data', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recognition_id')->constrained('ai_recognition_history');
            $table->string('field_name');
            $table->longText('original_value')->nullable();
            $table->longText('corrected_value')->nullable();
            $table->enum('correction_type', ['text','classification','confidence']);
            $table->longText('user_notes')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_training_data');
    }
};
