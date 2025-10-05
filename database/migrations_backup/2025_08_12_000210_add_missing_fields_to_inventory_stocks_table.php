<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('inventory_stocks', function (Blueprint $table) {
            $table->timestamp('last_counted_at')->nullable()->after('average_cost');
            $table->unsignedBigInteger('last_counted_by')->nullable()->after('last_counted_at');
            $table->text('bin_location')->nullable()->after('last_counted_by');
            $table->index('last_counted_by');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_stocks', function (Blueprint $table) {
            $table->dropIndex(['last_counted_by']);
            $table->dropColumn(['last_counted_at','last_counted_by','bin_location']);
        });
    }
};


