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
        Schema::create('import_sessions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('user_id');
            $table->string('status')->default('pending'); // pending, analyzing, mapping, conflicts, ready, importing, completed, failed
            $table->string('original_name');
            $table->string('stored_name');
            $table->string('file_type');
            $table->unsignedBigInteger('file_size');
            $table->timestamp('uploaded_at');
            $table->json('meta')->nullable(); // for any extra info
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('import_sessions');
    }
};
