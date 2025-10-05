<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('inventory_transactions', function (Blueprint $table) {
            $table->unsignedBigInteger('from_location_id')->nullable()->after('location_id');
            $table->unsignedBigInteger('to_location_id')->nullable()->after('from_location_id');
            $table->string('reference_type')->nullable()->after('total_cost');
            $table->unsignedBigInteger('reference_id')->nullable()->after('reference_type');
            $table->index(['from_location_id','to_location_id']);
            $table->index(['reference_type','reference_id']);
        });
    }

    public function down(): void
    {
        Schema::table('inventory_transactions', function (Blueprint $table) {
            $table->dropIndex(['from_location_id','to_location_id']);
            $table->dropIndex(['reference_type','reference_id']);
            $table->dropColumn(['from_location_id','to_location_id','reference_type','reference_id']);
        });
    }
};


