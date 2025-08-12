<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->text('vendor_name')->nullable()->after('supplier_id');
            $table->text('vendor_contact')->nullable()->after('vendor_name');
            $table->date('actual_delivery_date')->nullable()->after('expected_date');
            $table->text('terms')->nullable()->after('shipping');
            $table->decimal('approval_threshold', 12, 2)->nullable()->after('total');
            $table->boolean('requires_approval')->default(false)->after('approval_threshold');
            $table->integer('approval_level')->default(0)->after('requires_approval');
            $table->json('approval_history')->nullable()->after('approval_level');
            // MySQL cannot default TEXT, use VARCHAR for status with default
            $table->string('email_status', 50)->default('not_sent')->after('approval_history');
            $table->timestamp('last_email_sent_at')->nullable()->after('email_status');
            $table->unsignedBigInteger('template_id')->nullable()->after('last_email_sent_at');
            $table->index('template_id');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropIndex(['template_id']);
            $table->dropColumn([
                'vendor_name','vendor_contact','actual_delivery_date','terms',
                'approval_threshold','requires_approval','approval_level','approval_history',
                'email_status','last_email_sent_at','template_id'
            ]);
        });
    }
};


