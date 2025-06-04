<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Fix term_prompt table foreign keys
        Schema::table('term_prompt', function (Blueprint $table) {
            // Drop the old foreign key
            $table->dropForeign('keyword_prompt_prompt_id_foreign');
            
            // Add new foreign key with correct name
            $table->foreign('prompt_id')->references('id')->on('prompts')->onDelete('cascade');
        });
        
        // Ensure the engine is properly set
        DB::statement('ALTER TABLE `term_prompt` ENGINE=InnoDB');
    }

    public function down(): void
    {
        // Revert changes if needed
        Schema::table('term_prompt', function (Blueprint $table) {
            // Drop the new foreign key
            $table->dropForeign(['prompt_id']);
            
            // Add back the old foreign key
            $table->foreign('prompt_id', 'keyword_prompt_prompt_id_foreign')->references('id')->on('prompts')->onDelete('cascade');
        });
    }
};
