<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use OpenAI\Laravel\Facades\OpenAI;
use App\Models\Organization;
use App\Models\OrganizationIndustry;

class DetermineOrganizationIndustries extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:determine-organization-industries';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Determine the industry for organizations using OpenAI';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $organizations = Organization::where('is_competitor', false)
            ->whereNull('industry_id')
            ->whereNotNull('website')
            ->get();

        if ($organizations->isEmpty()) {
            $this->info('No organizations found that need industry determination.');
            return;
        }

        // Get existing industries to provide as options
        $existingIndustries = OrganizationIndustry::orderBy('name')->pluck('name')->toArray();
        $industryList = empty($existingIndustries) ? 'None yet' : implode(', ', $existingIndustries);

        $this->info("Found {$organizations->count()} organizations to process.");
        $this->info("Existing industries in database: {$industryList}");

        $progressBar = $this->output->createProgressBar($organizations->count());
        $progressBar->start();

        foreach ($organizations as $organization) {
            try {
                $prompt = "Look at {$organization->website} and determine what industry they should be classified under.\n\n";
                $prompt .= "Here are the existing industries in our database: {$industryList}\n\n";
                $prompt .= "Please consider if any of these existing industries is a great fit for this organization. ";
                $prompt .= "If one of the existing industries is a perfect match, return exactly that industry name. ";
                $prompt .= "If none of the existing industries are a good fit, suggest the best industry name for this organization.\n\n";
                $prompt .= "Just return the industry name only.";

                $response = OpenAI::chat()->create([
                    'model' => 'gpt-4o',
                    'messages' => [
                        [
                            'role' => 'user',
                            'content' => $prompt
                        ]
                    ],
                    'max_tokens' => 50,
                    'temperature' => 0.3,
                ]);

                $industryName = trim($response->choices[0]->message->content);
                
                // Find or create the industry
                $industry = OrganizationIndustry::firstOrCreate(['name' => $industryName]);
                
                $organization->update(['industry_id' => $industry->id]);
                
                $this->newLine();
                $this->info("Updated {$organization->name}: {$industryName}");
                
            } catch (\Exception $e) {
                $this->newLine();
                $this->error("Failed to process {$organization->name}: {$e->getMessage()}");
            }

            $progressBar->advance();
            
            // Add a small delay to avoid rate limiting
            sleep(1);
        }

        $progressBar->finish();
        $this->newLine();
        $this->info('Industry determination complete!');
    }
}
