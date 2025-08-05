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
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->string('asset_id')->unique(); // Unique Asset ID
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedBigInteger('category_id')->nullable();
            $table->string('type')->nullable();
            $table->string('serial_number')->nullable()->unique();
            $table->string('model')->nullable();
            $table->string('manufacturer')->nullable();
            $table->string('capacity')->nullable();
            $table->date('purchase_date')->nullable();
            $table->decimal('purchase_price', 15, 2)->nullable();
            $table->decimal('depreciation', 15, 2)->nullable();
            $table->unsignedBigInteger('location_id')->nullable();
            $table->unsignedBigInteger('department_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable(); // Assigned user
            $table->unsignedBigInteger('company_id');
            $table->string('warranty')->nullable();
            $table->string('insurance')->nullable();
            $table->decimal('health_score', 5, 2)->default(100.00);
            $table->string('status')->default('active');
            $table->string('qr_code_path')->nullable();
            $table->json('meta')->nullable(); // For extensibility
            $table->timestamps();
            $table->softDeletes();
            

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};
