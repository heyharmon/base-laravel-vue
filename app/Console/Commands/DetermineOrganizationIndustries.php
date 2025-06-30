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

        $this->info("Found {$organizations->count()} organizations to process.");

        $progressBar = $this->output->createProgressBar($organizations->count());
        $progressBar->start();

        foreach ($organizations as $organization) {
            try {
                $response = OpenAI::chat()->create([
                    'model' => 'gpt-4o',
                    'messages' => [
                        [
                            'role' => 'user',
                            'content' => "Look at {$organization->website} and tell me what industry they should be classified under. Just return the industry."
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
