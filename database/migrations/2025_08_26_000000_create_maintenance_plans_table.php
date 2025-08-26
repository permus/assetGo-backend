<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->unsignedInteger('priority_id')->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->text('descriptions')->nullable();
            $table->unsignedInteger('category_id')->nullable();
            $table->enum('plan_type', ['preventive','predictive','condition_based'])->default('preventive');
            $table->unsignedInteger('estimeted_duration')->nullable();
            $table->text('instractions')->nullable();
            $table->text('safety_notes')->nullable();
            $table->json('asset_ids')->nullable();
            $table->enum('frequency_type', ['time','usage','condition'])->default('time');
            $table->unsignedInteger('frequency_value')->nullable();
            $table->enum('frequency_unit', ['days','weeks','months','years'])->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active','plan_type']);
            $table->index('priority_id');
            $table->index('category_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_plans');
    }
};


