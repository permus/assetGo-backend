<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_checklist_responses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('schedule_maintenance_assigned_id');
            $table->unsignedBigInteger('checklist_item_id');
            $table->unsignedBigInteger('user_id');
            $table->enum('response_type', ['checkbox', 'measurements', 'text_input', 'photo_capture', 'pass_fail']);
            $table->json('response_value')->nullable();
            $table->string('photo_url')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            
            // Add indexes with shorter names
            $table->index('schedule_maintenance_assigned_id', 'idx_mcr_sma_id');
            $table->index('checklist_item_id', 'idx_mcr_checklist_item');
            $table->index('user_id', 'idx_mcr_user_id');

            $table->foreign('schedule_maintenance_assigned_id', 'fk_mcr_sma_id')
                ->references('id')
                ->on('schedule_maintenance_assigned')
                ->onDelete('cascade');
            
            $table->foreign('checklist_item_id', 'fk_mcr_checklist_item')
                ->references('id')
                ->on('maintenance_plans_checklists')
                ->onDelete('cascade');
            
            $table->foreign('user_id', 'fk_mcr_user_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');
            
            // Ensure one response per checklist item per assignment
            $table->unique(['schedule_maintenance_assigned_id', 'checklist_item_id'], 'uniq_mcr_sma_checklist');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_checklist_responses');
    }
};
