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
            Schema::table('keyword_prompt', function (Blueprint $table) {
                // First drop the foreign key constraint
                $table->dropForeign(['keyword_id']);
                // Then drop the unique index
                $table->dropUnique(['keyword_id', 'prompt_id']);
            });

            Schema::rename('keyword_prompt', 'term_prompt');

            Schema::table('term_prompt', function (Blueprint $table) {
                $table->renameColumn('keyword_id', 'term_id');
                // Add the foreign key back, now referencing terms table
                $table->foreign('term_id')->references('id')->on('terms')->onDelete('cascade');
                $table->unique(['term_id', 'prompt_id']);
            });
        }

        if (Schema::hasTable('keyword_response')) {
            Schema::rename('keyword_response', 'term_response');
        }

        // Handle the case where the table was renamed but the columns were not
        if (Schema::hasTable('term_prompt') && Schema::hasColumn('term_prompt', 'keyword_id')) {
            Schema::table('term_prompt', function (Blueprint $table) {
                $table->dropUnique(['keyword_id', 'prompt_id']);
                $table->renameColumn('keyword_id', 'term_id');
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
        if (Schema::hasTable('term_prompt')) {
            if (Schema::hasColumn('term_prompt', 'term_id')) {
                Schema::table('term_prompt', function (Blueprint $table) {
                    // Drop foreign key first
                    $table->dropForeign(['term_id']);
                    $table->dropUnique(['term_id', 'prompt_id']);
                });
            } else {
                Schema::table('term_prompt', function (Blueprint $table) {
                    $table->dropUnique(['keyword_id', 'prompt_id']);
                });
            }

            Schema::rename('term_prompt', 'keyword_prompt');

            if (Schema::hasColumn('keyword_prompt', 'term_id')) {
                Schema::table('keyword_prompt', function (Blueprint $table) {
                    $table->renameColumn('term_id', 'keyword_id');
                });
            }

            Schema::table('keyword_prompt', function (Blueprint $table) {
                // Add back the foreign key to keywords table
                $table->foreign('keyword_id')->references('id')->on('keywords')->onDelete('cascade');
                $table->unique(['keyword_id', 'prompt_id']);
            });
        }

        if (Schema::hasTable('term_response')) {
            if (Schema::hasColumn('term_response', 'term_id')) {
                Schema::table('term_response', function (Blueprint $table) {
                    $table->renameColumn('term_id', 'keyword_id');
                });
            }

            Schema::rename('term_response', 'keyword_response');
        }

        if (Schema::hasTable('terms')) {
            Schema::rename('terms', 'keywords');
        }
    }
};
