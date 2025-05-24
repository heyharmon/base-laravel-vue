<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Drop the foreign key constraint and column using raw SQL
        DB::statement('ALTER TABLE mentions DROP FOREIGN KEY mentions_keyword_id_foreign');
        DB::statement('ALTER TABLE mentions DROP INDEX mentions_keyword_id_response_id_unique');
        DB::statement('ALTER TABLE mentions DROP COLUMN keyword_id');
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

