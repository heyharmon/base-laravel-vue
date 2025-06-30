<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use App\Models\Organization;
use App\Models\OrganizationIndustry;

return new class extends Migration
{
	/**
	 * Run the migrations.
	 */
	public function up(): void
	{
		// Add temporary column first
		Schema::table('organizations', function (Blueprint $table) {
			$table->unsignedBigInteger('industry_temp_id')->nullable()->after('industry');
		});

		// Migrate existing industry data to the industries table
		$organizations = Organization::whereNotNull('industry')->get();

		foreach ($organizations as $organization) {
			if (!empty($organization->industry)) {
				$industry = OrganizationIndustry::firstOrCreate(['name' => $organization->industry]);
				$organization->update(['industry_temp_id' => $industry->id]);
			}
		}

		// Add the new foreign key column without constraint first
		Schema::table('organizations', function (Blueprint $table) {
			$table->unsignedBigInteger('industry_id')->nullable()->after('team_id');
		});

		// Copy the temp data to the new column
		Organization::whereNotNull('industry_temp_id')->update([
			'industry_id' => DB::raw('industry_temp_id')
		]);

		// Add the foreign key constraint
		Schema::table('organizations', function (Blueprint $table) {
			$table->foreign('industry_id')->references('id')->on('organization_industries');
		});

		// Remove old columns
		Schema::table('organizations', function (Blueprint $table) {
			$table->dropColumn(['industry', 'industry_temp_id']);
		});
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void
	{
		// Add back the original industry string column
		Schema::table('organizations', function (Blueprint $table) {
			$table->string('industry')->nullable()->after('industry_id');
		});

		// Copy industry names back from the relationship
		$organizations = Organization::with('organizationIndustry')->get();
		foreach ($organizations as $organization) {
			if ($organization->organizationIndustry) {
				$organization->update(['industry' => $organization->organizationIndustry->name]);
			}
		}

		// Drop the foreign key column
		Schema::table('organizations', function (Blueprint $table) {
			$table->dropForeign(['industry_id']);
			$table->dropColumn('industry_id');
		});
	}
};
