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
		Schema::table('articles', function (Blueprint $table) {
			$table->integer('perplexity_checks')->default(0)->after('perplexity_status');
		});
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void
	{
		Schema::table('articles', function (Blueprint $table) {
			$table->dropColumn('perplexity_checks');
		});
	}
};
