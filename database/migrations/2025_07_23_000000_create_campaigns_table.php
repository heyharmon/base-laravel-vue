<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use App\Models\Team;
use App\Models\Campaign;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Create campaigns table
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            // Add index for performance
            $table->index(['team_id', 'is_default']);
        });

        // Add campaign_id to organizations table (for competitors only)
        Schema::table('organizations', function (Blueprint $table) {
            $table->foreignId('campaign_id')->nullable()->after('team_id')->constrained()->nullOnDelete();
            $table->index('campaign_id');
        });

        // Add campaign_id to prompts table
        Schema::table('prompts', function (Blueprint $table) {
            $table->foreignId('campaign_id')->nullable()->after('team_id')->constrained()->cascadeOnDelete();
            $table->index('campaign_id');
        });

        // Add campaign_id to articles table
        Schema::table('articles', function (Blueprint $table) {
            $table->foreignId('campaign_id')->nullable()->after('team_id')->constrained()->cascadeOnDelete();
            $table->index('campaign_id');
        });

        // Create default campaigns for existing teams and migrate data
        $teams = Team::all();

        foreach ($teams as $team) {
            // Create default campaign for this team
            $campaign = Campaign::create([
                'team_id' => $team->id,
                'name' => 'Default Campaign',
                'description' => 'Default campaign for existing data',
                'is_default' => true,
            ]);

            // Update competitors (organizations where is_competitor = true) to belong to default campaign
            DB::table('organizations')
                ->where('team_id', $team->id)
                ->where('is_competitor', true)
                ->update(['campaign_id' => $campaign->id]);

            // Update all prompts to belong to default campaign
            DB::table('prompts')
                ->where('team_id', $team->id)
                ->update(['campaign_id' => $campaign->id]);

            // Update all articles to belong to default campaign
            DB::table('articles')
                ->where('team_id', $team->id)
                ->update(['campaign_id' => $campaign->id]);
        }

        // Make campaign_id required for prompts and articles after migration
        Schema::table('prompts', function (Blueprint $table) {
            $table->foreignId('campaign_id')->nullable(false)->change();
        });

        Schema::table('articles', function (Blueprint $table) {
            $table->foreignId('campaign_id')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove foreign key constraints and columns
        Schema::table('articles', function (Blueprint $table) {
            $table->dropForeign(['campaign_id']);
            $table->dropColumn('campaign_id');
        });

        Schema::table('prompts', function (Blueprint $table) {
            $table->dropForeign(['campaign_id']);
            $table->dropColumn('campaign_id');
        });

        Schema::table('organizations', function (Blueprint $table) {
            $table->dropForeign(['campaign_id']);
            $table->dropColumn('campaign_id');
        });

        // Drop campaigns table
        Schema::dropIfExists('campaigns');
    }
};
