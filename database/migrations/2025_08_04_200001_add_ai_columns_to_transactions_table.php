<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->boolean('is_ai_categorized')->default(false);
            $table->timestamp('ai_categorized_at')->nullable();
            $table->index(['user_id', 'is_ai_categorized']);
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'is_ai_categorized']);
            $table->dropColumn(['is_ai_categorized', 'ai_categorized_at']);
        });
    }
};
