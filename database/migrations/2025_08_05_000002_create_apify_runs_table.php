<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('apify_runs', function (Blueprint $table) {
            $table->id();
            $table->string('apify_run_id')->unique();
            $table->string('status');
            $table->json('input_data');
            $table->integer('items_processed')->default(0);
            $table->integer('items_imported')->default(0);
            $table->integer('items_updated')->default(0);
            $table->integer('items_skipped')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->timestamps();

            $table->index('status');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('apify_runs');
    }
};
