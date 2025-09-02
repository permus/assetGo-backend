<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('module_definitions', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('display_name');
            $table->text('description')->nullable();
            $table->string('icon_name')->nullable();
            $table->string('route_path')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_system_module')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('module_definitions');
    }
};


