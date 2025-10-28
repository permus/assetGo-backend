<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->string('po_number')->unique();
            $table->unsignedBigInteger('supplier_id');
            $table->text('vendor_name')->nullable();
            $table->text('vendor_contact')->nullable();
            $table->date('order_date')->nullable();
            $table->date('expected_date')->nullable();
            $table->date('actual_delivery_date')->nullable();
            $table->enum('status', ['draft','pending','ordered','approved','rejected','closed','cancelled','received'])->default('draft');
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('tax', 12, 2)->default(0);
            $table->decimal('shipping', 12, 2)->default(0);
            $table->text('terms')->nullable();
            $table->text('notes')->nullable();
            $table->decimal('total', 12, 2)->default(0);
            $table->decimal('approval_threshold', 12, 2)->nullable();
            $table->boolean('requires_approval')->default(false);
            $table->integer('approval_level')->default(0);
            $table->json('approval_history')->nullable();
            $table->string('email_status', 50)->default('not_sent');
            $table->timestamp('last_email_sent_at')->nullable();
            $table->unsignedBigInteger('template_id')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('reject_comment')->nullable();
            $table->timestamps();
            $table->index(['company_id','supplier_id']);
            $table->index('template_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_orders');
    }
};


