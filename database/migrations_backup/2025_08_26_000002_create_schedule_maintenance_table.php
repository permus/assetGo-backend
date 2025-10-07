<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('schedule_maintenance')) {
            return;
        }

        Schema::create('schedule_maintenance', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('maintenance_plan_id')->index();
            $table->json('asset_ids')->nullable();
            // store full datetime to support time selection in UI
            $table->dateTime('start_date')->nullable();
            $table->dateTime('due_date')->nullable();
            $table->enum('status', ['scheduled','in_progress','completed'])->default('scheduled');
            $table->unsignedInteger('priority_id')->nullable();
            $table->timestamps();

            $table->index(['status']);
            $table->index(['due_date']);
            $table->foreign('maintenance_plan_id')->references('id')->on('maintenance_plans')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_maintenance');
    }
};


