<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
	public function up(): void
	{
		// Skip this migration for SQLite (used in tests)
		if (DB::connection()->getDriverName() === 'sqlite') {
			return;
		}

		// Fix term_response table foreign keys and indexes
		Schema::table('term_response', function (Blueprint $table) {
			// Drop the old foreign keys
			$table->dropForeign('keyword_response_keyword_id_foreign');
			$table->dropForeign('keyword_response_response_id_foreign');

			// Add new foreign keys with correct names
			$table->foreign('term_id')->references('id')->on('terms')->onDelete('cascade');
			$table->foreign('response_id')->references('id')->on('responses')->onDelete('cascade');
		});

		// Check if the Response model is still trying to use keyword_response table
		if (Schema::hasTable('term_response') && !Schema::hasTable('keyword_response')) {
			// Update the model relationship in the database
			DB::statement('ALTER TABLE `term_response` ENGINE=InnoDB');
		}
	}

	public function down(): void
	{
		// Skip this migration for SQLite (used in tests)
		if (DB::connection()->getDriverName() === 'sqlite') {
			return;
		}

		// Revert changes if needed
		Schema::table('term_response', function (Blueprint $table) {
			// Drop the new foreign keys
			$table->dropForeign(['term_id']);
			$table->dropForeign(['response_id']);

			// Add back the old foreign keys
			$table->foreign('term_id', 'keyword_response_keyword_id_foreign')->references('id')->on('terms')->onDelete('cascade');
			$table->foreign('response_id', 'keyword_response_response_id_foreign')->references('id')->on('responses')->onDelete('cascade');
		});
	}
};
