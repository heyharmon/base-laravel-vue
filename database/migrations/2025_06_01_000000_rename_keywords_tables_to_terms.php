<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('keywords')) {
            Schema::rename('keywords', 'terms');
        }

        if (Schema::hasTable('keyword_prompt')) {
            Schema::rename('keyword_prompt', 'term_prompt');
        }

        if (Schema::hasTable('keyword_response')) {
            Schema::rename('keyword_response', 'term_response');
        }

        if (Schema::hasTable('term_prompt') && Schema::hasColumn('term_prompt', 'keyword_id')) {
            Schema::table('term_prompt', function (Blueprint $table) {
                $table->renameColumn('keyword_id', 'term_id');
                $table->dropUnique(['keyword_id', 'prompt_id']);
                $table->unique(['term_id', 'prompt_id']);
            });
        }

        if (Schema::hasTable('term_response') && Schema::hasColumn('term_response', 'keyword_id')) {
            Schema::table('term_response', function (Blueprint $table) {
                $table->renameColumn('keyword_id', 'term_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('term_prompt') && Schema::hasColumn('term_prompt', 'term_id')) {
            Schema::table('term_prompt', function (Blueprint $table) {
                $table->dropUnique(['term_id', 'prompt_id']);
                $table->renameColumn('term_id', 'keyword_id');
                $table->unique(['keyword_id', 'prompt_id']);
            });
        }

        if (Schema::hasTable('term_response') && Schema::hasColumn('term_response', 'term_id')) {
            Schema::table('term_response', function (Blueprint $table) {
                $table->renameColumn('term_id', 'keyword_id');
            });
        }

        if (Schema::hasTable('term_prompt')) {
            Schema::rename('term_prompt', 'keyword_prompt');
        }

        if (Schema::hasTable('term_response')) {
            Schema::rename('term_response', 'keyword_response');
        }

        if (Schema::hasTable('terms')) {
            Schema::rename('terms', 'keywords');
        }
    }
};
