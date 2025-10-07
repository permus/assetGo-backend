<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedule_maintenance_assigned', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('schedule_maintenance_id')->index();
            $table->unsignedBigInteger('team_id')->index();
            $table->timestamps();

            $table->foreign('schedule_maintenance_id')->references('id')->on('schedule_maintenance')->onDelete('cascade');
            // team_id references teams table if exists; keeping as plain index for optional FK
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_maintenance_assigned');
    }
};


