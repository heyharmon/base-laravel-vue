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
			$table->string('perplexity_request_id')->nullable()->after('content');
			$table->string('perplexity_status')->nullable()->after('perplexity_request_id');
		});
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void
	{
		Schema::table('articles', function (Blueprint $table) {
			$table->dropColumn(['perplexity_request_id', 'perplexity_status']);
		});
	}
};
