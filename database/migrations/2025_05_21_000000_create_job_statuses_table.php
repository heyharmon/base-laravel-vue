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
        Schema::create('job_statuses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->nullable()->constrained()->onDelete('cascade');
            $table->uuid('job_id')->index();
            $table->string('job_class');
            $table->string('job_batch_id')->nullable()->index();
            $table->string('status')->default('pending'); // pending, processing, completed, failed
            $table->text('output')->nullable();
            $table->text('error')->nullable();
            $table->integer('progress')->default(0); // 0-100 percent
            
            // Polymorphic relationship for the model being processed
            $table->morphs('trackable');
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('job_statuses');
    }
};
