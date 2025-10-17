<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('inventory_stocks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('part_id');
            $table->unsignedBigInteger('location_id');
            $table->integer('on_hand')->default(0);
            $table->integer('reserved')->default(0);
            $table->integer('available')->default(0);
            $table->decimal('average_cost', 12, 2)->default(0);
            $table->timestamp('last_counted_at')->nullable();
            $table->unsignedBigInteger('last_counted_by')->nullable();
            $table->text('bin_location')->nullable();
            $table->timestamps();
            $table->unique(['company_id','part_id','location_id']);
            $table->index(['company_id','location_id']);
            $table->index('last_counted_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_stocks');
    }
};


