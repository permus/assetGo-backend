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
        Schema::table('companies', function (Blueprint $table) {
            if (!Schema::hasColumn('companies', 'currency')) {
                $table->string('currency', 3)->nullable()->index()->after('subscription_expires_at');
            }
            if (!Schema::hasColumn('companies', 'settings')) {
                $table->json('settings')->nullable()->after('currency');
            }
            // Reuse existing 'logo' column for company logo path/URL; no 'logo_url' added to avoid duplication
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            if (Schema::hasColumn('companies', 'settings')) {
                $table->dropColumn('settings');
            }
            if (Schema::hasColumn('companies', 'currency')) {
                $table->dropIndex(['currency']);
                $table->dropColumn('currency');
            }
        });
    }
};


