<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Cannot change enum easily across DBs; store allowed statuses in code and avoid altering here.
        // We add no-op migration to track requirement; controller/model should allow 'received'.
    }

    public function down(): void
    {
        // no-op
    }
};


