<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_tag_pivot', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('asset_id');
            $table->unsignedBigInteger('tag_id');
            $table->timestamps();

            $table->foreign('asset_id')->references('id')->on('assets')->onDelete('cascade');
            $table->foreign('tag_id')->references('id')->on('asset_tags')->onDelete('cascade');
            $table->unique(['asset_id', 'tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_tag_pivot');
    }
}; 