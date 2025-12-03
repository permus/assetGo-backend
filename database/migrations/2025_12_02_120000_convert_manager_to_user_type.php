<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Convert all 'manager' user_type to 'user'
        DB::table('users')
            ->where('user_type', 'manager')
            ->update(['user_type' => 'user']);
        
        // Convert all 'owner' user_type to 'admin'
        DB::table('users')
            ->where('user_type', 'owner')
            ->update(['user_type' => 'admin']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Note: We can't reliably reverse this as we don't know which users were originally 'manager' or 'owner'
        // This migration is one-way
    }
};

