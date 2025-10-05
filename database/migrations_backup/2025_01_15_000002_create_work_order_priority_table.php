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
        Schema::create('work_order_priority', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->string('name', 100)->unique();                  // e.g., "low"
            $table->string('slug', 100)->unique();
            $table->boolean('is_management')->default(false);       // true => cannot delete
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
            
            $table->index(['company_id', 'is_management']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('work_order_priority');
    }
};
