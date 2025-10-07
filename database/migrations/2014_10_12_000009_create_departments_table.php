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
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Primary name like "IT Department"
            $table->text('description')->nullable(); // Secondary description like "Information Technology and Systems"
            $table->unsignedBigInteger('company_id')->nullable(); // Foreign key to companies table
            $table->unsignedBigInteger('manager_id')->nullable(); // Department manager
            $table->unsignedBigInteger('user_id')->nullable(); // User who created the department
            $table->string('code')->nullable(); // Department code/abbreviation
            $table->boolean('is_active')->default(true); // Whether department is active
            $table->integer('sort_order')->default(0); // For ordering in dropdowns
            $table->timestamps();


        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('departments');
    }
};
