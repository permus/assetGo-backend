<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->text('business_name')->nullable()->after('company_id');
            $table->text('supplier_code')->nullable()->after('business_name');
            $table->text('city')->nullable()->after('address');
            $table->text('country')->nullable()->after('city');
            $table->text('currency')->nullable()->after('country');
            $table->boolean('is_active')->default(true)->after('currency');
            $table->boolean('is_approved')->default(true)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropColumn(['business_name','supplier_code','city','country','currency','is_active','is_approved']);
        });
    }
};


