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
		// Drop foreign key constraint and industry_id column from organizations table
		Schema::table('organizations', function (Blueprint $table) {
			$table->dropForeign(['industry_id']);
			$table->dropColumn('industry_id');
		});

		// Drop the organization_industries table
		Schema::dropIfExists('organization_industries');
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void
	{
		// Recreate the organization_industries table
		Schema::create('organization_industries', function (Blueprint $table) {
			$table->id();
			$table->string('name')->unique();
			$table->timestamps();
		});

		// Add back the industry_id column and foreign key constraint
		Schema::table('organizations', function (Blueprint $table) {
			$table->unsignedBigInteger('industry_id')->nullable()->after('team_id');
			$table->foreign('industry_id')->references('id')->on('organization_industries');
		});
	}
};
