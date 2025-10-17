<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->text('business_name')->nullable();
            $table->text('supplier_code')->nullable();
            $table->string('name');
            $table->string('contact_person')->nullable();
            $table->string('tax_registration_number')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('alternate_phone')->nullable();
            $table->string('website')->nullable();
            $table->string('street_address')->nullable();
            $table->text('address')->nullable();
            $table->text('city')->nullable();
            $table->string('state')->nullable();
            $table->string('postal_code')->nullable();
            $table->text('country')->nullable();
            $table->text('currency')->nullable();
            $table->text('terms')->nullable();
            $table->text('payment_terms')->nullable();
            $table->decimal('credit_limit', 15, 2)->nullable();
            $table->integer('delivery_lead_time')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_approved')->default(true);
            $table->json('extra')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suppliers');
    }
};


