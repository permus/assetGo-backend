<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_plans_checklists', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('maintenance_plan_id')->index();
            $table->string('title');
            $table->enum('type', ['checkbox','measurements','text_input','photo_capture','pass_fail']);
            $table->text('description')->nullable();
            $table->boolean('is_required')->default(false);
            $table->boolean('is_safety_critical')->default(false);
            $table->boolean('is_photo_required')->default(false);
            $table->unsignedInteger('order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('maintenance_plan_id')->references('id')->on('maintenance_plans')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_plans_checklists');
    }
};


