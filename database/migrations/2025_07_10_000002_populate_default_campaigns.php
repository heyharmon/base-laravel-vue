<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\Team;
use App\Models\Campaign;
use App\Models\Prompt;
use App\Models\Organization;
use App\Models\Article;

return new class extends Migration
{
	/**
	 * Run the migrations.
	 */
	public function up(): void
	{
		// Create default campaigns for existing teams that don't have one
		$teamsWithoutCampaigns = Team::whereDoesntHave('campaigns')->get();

		foreach ($teamsWithoutCampaigns as $team) {
			$defaultCampaign = $team->campaigns()->create([
				'name' => 'Default Campaign',
				'description' => 'Default campaign for ' . $team->name,
				'is_default' => true,
			]);

			// Update existing prompts to belong to the default campaign
			Prompt::where('team_id', $team->id)
				->whereNull('campaign_id')
				->update(['campaign_id' => $defaultCampaign->id]);

			// Update existing organizations to belong to the default campaign
			Organization::where('team_id', $team->id)
				->whereNull('campaign_id')
				->update(['campaign_id' => $defaultCampaign->id]);

			// Update existing articles to belong to the default campaign
			Article::where('team_id', $team->id)
				->whereNull('campaign_id')
				->update(['campaign_id' => $defaultCampaign->id]);
		}

		// For teams that already have campaigns (created after the Team model was updated),
		// assign existing records to the default campaign
		$teamsWithCampaigns = Team::whereHas('campaigns')->get();

		foreach ($teamsWithCampaigns as $team) {
			$defaultCampaign = $team->campaigns()->where('is_default', true)->first();

			if ($defaultCampaign) {
				// Update existing prompts to belong to the default campaign
				Prompt::where('team_id', $team->id)
					->whereNull('campaign_id')
					->update(['campaign_id' => $defaultCampaign->id]);

				// Update existing organizations to belong to the default campaign
				Organization::where('team_id', $team->id)
					->whereNull('campaign_id')
					->update(['campaign_id' => $defaultCampaign->id]);

				// Update existing articles to belong to the default campaign
				Article::where('team_id', $team->id)
					->whereNull('campaign_id')
					->update(['campaign_id' => $defaultCampaign->id]);
			}
		}
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void
	{
		// Set all campaign_id fields to null
		Prompt::query()->update(['campaign_id' => null]);
		Organization::query()->update(['campaign_id' => null]);
		Article::query()->update(['campaign_id' => null]);

		// Delete all campaigns
		Campaign::query()->delete();
	}
};
