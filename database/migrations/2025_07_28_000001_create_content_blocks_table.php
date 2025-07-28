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
        Schema::create('content_blocks', function (Blueprint $table) {
            $table->id();
            $table->morphs('blockable');
            $table->string('block_type');
            $table->json('block_data');
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['blockable_id', 'blockable_type', 'sort_order']);
            $table->index('block_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('content_blocks');
    }
};
