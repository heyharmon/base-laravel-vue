<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
	/**
	 * Run the migrations.
	 * Add performance indexes for OrganizationVisibilityController optimization
	 */
	public function up(): void
	{
		// 1. Composite indexes for responses table (most critical for performance)
		Schema::table('responses', function (Blueprint $table) {
			// Index for prompt_id and created_at (for filtering responses by team and date)
			$table->index(['prompt_id', 'created_at'], 'idx_responses_prompt_created');

			// Reverse index for created_at and prompt_id (for date-first queries)
			$table->index(['created_at', 'prompt_id'], 'idx_responses_created_prompt');
		});

		// 2. Composite indexes for term_response pivot table (critical for joins)
		Schema::table('term_response', function (Blueprint $table) {
			// Index for term_id and response_id (for finding responses by terms)
			$table->index(['term_id', 'response_id'], 'idx_term_response_term_response');

			// Reverse index for response_id and term_id (for finding terms by response)
			$table->index(['response_id', 'term_id'], 'idx_term_response_response_term');
		});

		// 3. Single column indexes for foreign keys (Laravel may have already created these, but adding them is safe)
		try {
			Schema::table('prompts', function (Blueprint $table) {
				$table->index('team_id', 'idx_prompts_team_id');
			});
		} catch (\Exception $e) {
			// Index likely already exists from foreign key constraint
		}

		try {
			Schema::table('terms', function (Blueprint $table) {
				$table->index('organization_id', 'idx_terms_organization_id');
			});
		} catch (\Exception $e) {
			// Index likely already exists from foreign key constraint
		}

		try {
			Schema::table('organizations', function (Blueprint $table) {
				$table->index('team_id', 'idx_organizations_team_id');
			});
		} catch (\Exception $e) {
			// Index likely already exists from foreign key constraint
		}
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void
	{
		// Drop composite indexes for responses table
		Schema::table('responses', function (Blueprint $table) {
			$table->dropIndex('idx_responses_prompt_created');
			$table->dropIndex('idx_responses_created_prompt');
		});

		// Drop composite indexes for term_response pivot table
		Schema::table('term_response', function (Blueprint $table) {
			$table->dropIndex('idx_term_response_term_response');
			$table->dropIndex('idx_term_response_response_term');
		});

		// Drop single column indexes (use try-catch in case they don't exist)
		try {
			Schema::table('prompts', function (Blueprint $table) {
				$table->dropIndex('idx_prompts_team_id');
			});
		} catch (\Exception $e) {
			// Index may not have been created if it already existed
		}

		try {
			Schema::table('terms', function (Blueprint $table) {
				$table->dropIndex('idx_terms_organization_id');
			});
		} catch (\Exception $e) {
			// Index may not have been created if it already existed
		}

		try {
			Schema::table('organizations', function (Blueprint $table) {
				$table->dropIndex('idx_organizations_team_id');
			});
		} catch (\Exception $e) {
			// Index may not have been created if it already existed
		}
	}
};
