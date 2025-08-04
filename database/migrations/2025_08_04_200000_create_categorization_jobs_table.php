<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categorization_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('batch_id')->index();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->enum('type', ['single', 'batch', 'all']);
            $table->integer('total_transactions')->default(0);
            $table->integer('processed_transactions')->default(0);
            $table->integer('successful_transactions')->default(0);
            $table->integer('failed_transactions')->default(0);
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->json('failed_transaction_ids')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categorization_jobs');
    }
};
