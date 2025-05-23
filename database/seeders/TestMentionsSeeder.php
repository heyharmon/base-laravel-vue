<?php

namespace Database\Seeders;

use App\Models\Keyword;
use App\Models\Mention;
use App\Models\Organization;
use App\Models\Prompt;
use App\Models\Response;
use App\Models\Team;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class TestMentionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get all teams
        $teams = Team::all();
        
        foreach ($teams as $team) {
            // Get all organizations for this team
            $organizations = Organization::where('team_id', $team->id)->get();
            
            // Get all prompts for this team
            $prompts = Prompt::where('team_id', $team->id)->get();
            
            // Skip if no prompts or organizations
            if ($prompts->isEmpty() || $organizations->isEmpty()) {
                continue;
            }
            
            // Get all responses for this team's prompts
            $responses = Response::whereIn('prompt_id', $prompts->pluck('id'))->get();
            
            // Skip if no responses
            if ($responses->isEmpty()) {
                continue;
            }
            
            // Get all keywords for this team's organizations
            $keywords = Keyword::whereIn('organization_id', $organizations->pluck('id'))->get();
            
            // Skip if no keywords
            if ($keywords->isEmpty()) {
                continue;
            }
            
            // Create mentions for each keyword-response pair
            foreach ($responses as $response) {
                // Get the prompt for this response
                $prompt = $prompts->firstWhere('id', $response->prompt_id);
                
                // Randomly select 0-3 keywords to mention in this response
                $mentionCount = rand(0, 3);
                $selectedKeywords = $keywords->random(min($mentionCount, $keywords->count()));
                
                foreach ($selectedKeywords as $keyword) {
                    // Create a mention record
                    Mention::create([
                        'keyword_id' => $keyword->id,
                        'response_id' => $response->id,
                        'prompt_id' => $prompt->id,
                        'organization_id' => $keyword->organization_id,
                        'team_id' => $team->id,
                    ]);
                    
                    // Also make sure the keyword is attached to the response
                    // This simulates what happens in the RunPromptJob
                    $response->keywords()->syncWithoutDetaching([$keyword->id]);
                }
            }
        }
    }
}
