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
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('user_id'); // recipient
            $table->enum('type', ['asset', 'location', 'work_order', 'team', 'maintenance', 'inventory', 'report', 'settings']);
            $table->string('action'); // e.g., 'created', 'updated', 'deleted', 'assigned', etc.
            $table->string('title');
            $table->text('message');
            $table->json('data')->nullable(); // stores entity ID, name, and other relevant info
            $table->timestamp('read_at')->nullable();
            $table->boolean('read')->default(false);
            $table->unsignedBigInteger('created_by')->nullable(); // who triggered the notification
            $table->timestamps();

            // Foreign keys
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');

            // Indexes for performance
            $table->index('company_id');
            $table->index('user_id');
            $table->index('read');
            $table->index('created_at');
            $table->index('type');
            $table->index(['user_id', 'read']);
            $table->index(['company_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
