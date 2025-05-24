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
        Schema::table('mentions', function (Blueprint $table) {
            // Drop the foreign key constraint and column
            $table->dropForeign(['keyword_id']);
            $table->dropColumn('keyword_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mentions', function (Blueprint $table) {
            // Add back the keyword_id column
            $table->foreignId('keyword_id')->constrained()->cascadeOnDelete();
            // Add back the unique constraint
            $table->unique(['keyword_id', 'response_id']);
        });
    }
};

