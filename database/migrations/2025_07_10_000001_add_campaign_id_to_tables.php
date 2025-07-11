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
		Schema::table('prompts', function (Blueprint $table) {
			$table->foreignId('campaign_id')->after('team_id')->nullable()->constrained()->onDelete('cascade');
		});

		Schema::table('organizations', function (Blueprint $table) {
			$table->foreignId('campaign_id')->after('team_id')->nullable()->constrained()->onDelete('cascade');
		});

		Schema::table('articles', function (Blueprint $table) {
			$table->foreignId('campaign_id')->after('team_id')->nullable()->constrained()->onDelete('cascade');
		});
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void
	{
		Schema::table('prompts', function (Blueprint $table) {
			$table->dropForeign(['campaign_id']);
			$table->dropColumn('campaign_id');
		});

		Schema::table('organizations', function (Blueprint $table) {
			$table->dropForeign(['campaign_id']);
			$table->dropColumn('campaign_id');
		});

		Schema::table('articles', function (Blueprint $table) {
			$table->dropForeign(['campaign_id']);
			$table->dropColumn('campaign_id');
		});
	}
};
