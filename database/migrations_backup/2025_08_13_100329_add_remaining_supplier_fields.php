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
        Schema::table('suppliers', function (Blueprint $table) {
            // Add only the fields that don't already exist
            if (!Schema::hasColumn('suppliers', 'contact_person')) {
                $table->string('contact_person')->nullable()->after('supplier_code');
            }
            if (!Schema::hasColumn('suppliers', 'tax_registration_number')) {
                $table->string('tax_registration_number')->nullable()->after('contact_person');
            }
            if (!Schema::hasColumn('suppliers', 'alternate_phone')) {
                $table->string('alternate_phone')->nullable()->after('phone');
            }
            if (!Schema::hasColumn('suppliers', 'website')) {
                $table->string('website')->nullable()->after('alternate_phone');
            }
            if (!Schema::hasColumn('suppliers', 'payment_terms')) {
                $table->text('payment_terms')->nullable()->after('terms');
            }
            if (!Schema::hasColumn('suppliers', 'credit_limit')) {
                $table->decimal('credit_limit', 15, 2)->nullable()->after('currency');
            }
            if (!Schema::hasColumn('suppliers', 'delivery_lead_time')) {
                $table->integer('delivery_lead_time')->nullable()->after('credit_limit');
            }
            if (!Schema::hasColumn('suppliers', 'notes')) {
                $table->text('notes')->nullable()->after('delivery_lead_time');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropColumn([
                'contact_person', 'tax_registration_number', 'alternate_phone',
                'website', 'payment_terms', 'credit_limit', 'delivery_lead_time', 'notes'
            ]);
        });
    }
};
