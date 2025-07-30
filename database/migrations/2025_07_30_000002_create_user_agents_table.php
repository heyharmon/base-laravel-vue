<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('user_agents', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->text('user_agent_string');
            $table->string('user_agent_hash', 64)->unique();
            $table->enum('type', ['search_engine','ai_bot','social_media','other']);
            $table->enum('category', ['good_bot','bad_bot','unknown'])->default('unknown');
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['type','is_active']);
            $table->index(['category','is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_agents');
    }
};
