<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use App\Models\Organization;
use App\Models\Campaign;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add fields to campaigns table
        Schema::table('campaigns', function (Blueprint $table) {
            $table->string('location')->nullable()->after('description');
            $table->text('keywords')->nullable()->after('location');
        });

        // Migrate data from owned organizations to default campaigns
        $ownedOrganizations = Organization::where('is_competitor', false)
            ->whereNotNull('location')
            ->orWhere('is_competitor', false)
            ->whereNotNull('description')
            ->orWhere('is_competitor', false)
            ->whereNotNull('keywords')
            ->get();

        foreach ($ownedOrganizations as $organization) {
            // Find the default campaign for this team
            $defaultCampaign = Campaign::where('team_id', $organization->team_id)
                ->where('is_default', true)
                ->first();

            if ($defaultCampaign) {
                $defaultCampaign->update([
                    'location' => $organization->location,
                    'description' => $organization->description ?: $defaultCampaign->description,
                    'keywords' => $organization->keywords,
                ]);
            }
        }

        // Remove fields from organizations table
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn(['location', 'description', 'keywords']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Add fields back to organizations table
        Schema::table('organizations', function (Blueprint $table) {
            $table->string('location')->nullable();
            $table->string('description')->nullable();
            $table->text('keywords')->nullable();
        });

        // Migrate data back from default campaigns to owned organizations
        $defaultCampaigns = Campaign::where('is_default', true)
            ->where(function ($query) {
                $query->whereNotNull('location')
                    ->orWhereNotNull('keywords');
            })
            ->get();

        foreach ($defaultCampaigns as $campaign) {
            // Find the owned organization for this team
            $ownedOrganization = Organization::where('team_id', $campaign->team_id)
                ->where('is_competitor', false)
                ->first();

            if ($ownedOrganization) {
                $ownedOrganization->update([
                    'location' => $campaign->location,
                    'description' => $campaign->description,
                    'keywords' => $campaign->keywords,
                ]);
            }
        }

        // Remove fields from campaigns table
        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropColumn(['location', 'keywords']);
        });
    }
};
