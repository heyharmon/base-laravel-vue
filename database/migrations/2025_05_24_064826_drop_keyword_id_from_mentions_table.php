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
            // First drop the unique constraint
			$table->dropIndex('mentions_keyword_id_response_id_unique');

            // Then drop the foreign key and column in one step
            $table->dropConstrainedForeignId('keyword_id');
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

