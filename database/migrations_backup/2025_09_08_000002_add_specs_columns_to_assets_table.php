<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            if (!Schema::hasColumn('assets', 'brand')) {
                $table->string('brand')->nullable()->after('manufacturer');
            }
            if (!Schema::hasColumn('assets', 'dimensions')) {
                $table->string('dimensions')->nullable()->after('brand');
            }
            if (!Schema::hasColumn('assets', 'weight')) {
                $table->string('weight')->nullable()->after('dimensions');
            }
        });
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            if (Schema::hasColumn('assets', 'weight')) {
                $table->dropColumn('weight');
            }
            if (Schema::hasColumn('assets', 'dimensions')) {
                $table->dropColumn('dimensions');
            }
            if (Schema::hasColumn('assets', 'brand')) {
                $table->dropColumn('brand');
            }
        });
    }
};


