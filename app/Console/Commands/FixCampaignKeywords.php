<?php

namespace App\Console\Commands;

use App\Models\Campaign;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixCampaignKeywords extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'campaign:fix-keywords {--dry-run : Run the command in dry-run mode without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fix Campaign records where keywords are stored as strings instead of arrays';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $isDryRun = $this->option('dry-run');

        if ($isDryRun) {
            $this->info('🏃 Running in DRY-RUN mode - no changes will be made');
        }

        $this->info('Scanning campaigns for keyword string issues...');

        // Get all campaigns and check their keywords column directly from database
        $campaigns = DB::table('campaigns')
            ->whereNotNull('keywords')
            ->get();

        $problematicCampaigns = [];

        foreach ($campaigns as $campaign) {
            $keywordsValue = $campaign->keywords;

            $isProblematic = false;

            // Format 1: String with escaped quotes: "["keyword1", "keyword2"]"
            if (
                is_string($keywordsValue) &&
                str_starts_with($keywordsValue, '"[') &&
                str_ends_with($keywordsValue, ']"') &&
                str_contains($keywordsValue, '\"')
            ) {
                $isProblematic = true;
            }

            // Format 2: Array with single string element containing the actual array
            // Example: ["[\"keyword1\", \"keyword2\"]"]
            if (
                is_string($keywordsValue) &&
                str_starts_with($keywordsValue, '["[') &&
                str_ends_with($keywordsValue, ']"]') &&
                str_contains($keywordsValue, '\"')
            ) {
                $isProblematic = true;
            }

            if ($isProblematic) {
                $problematicCampaigns[] = $campaign;
            }
        }

        if (empty($problematicCampaigns)) {
            $this->info('✅ No campaigns found with keyword string issues.');
            return 0;
        }

        $this->info("Found {" . count($problematicCampaigns) . "} campaigns with keyword string issues:");

        $fixed = 0;
        $errors = 0;

        foreach ($problematicCampaigns as $campaign) {
            $this->info("Processing Campaign ID {$campaign->id}: {$campaign->name}");

            try {
                $keywordsString = $campaign->keywords;
                $keywordsArray = null;

                // Format 1: "["keyword1", "keyword2"]" with escaped quotes
                if (str_starts_with($keywordsString, '"[') && str_ends_with($keywordsString, ']"') && !str_starts_with($keywordsString, '["[')) {
                    // Remove the outer quotes (first 1 and last 1 characters)
                    $cleanedString = substr($keywordsString, 1, -1);
                    // Unescape the string to convert \" back to "
                    $unescapedString = stripcslashes($cleanedString);
                    // Decode the JSON string to get the actual array
                    $keywordsArray = json_decode($unescapedString, true);
                }
                // Format 2: ["[\"keyword1\",\"keyword2\"]"] - comma-separated quoted values
                elseif (str_starts_with($keywordsString, '["[') && str_ends_with($keywordsString, ']"]')) {
                    // Remove the outer array brackets and quotes: ["..."] -> "..."
                    $cleanedString = substr($keywordsString, 2, -2);

                    // This format is comma-separated quoted values, not proper JSON
                    // Split by "," pattern
                    $parts = explode('","', $cleanedString);
                    $keywordsArray = [];

                    foreach ($parts as $i => $part) {
                        // For first part, remove [\" at the beginning
                        if ($i === 0) {
                            $part = ltrim($part, '[\\');
                        }
                        // For last part, remove ] at the end
                        if ($i === count($parts) - 1) {
                            $part = rtrim($part, ']');
                        }

                        // Remove quotes and backslashes
                        $cleaned = trim($part, '"\\');
                        $keywordsArray[] = $cleaned;
                    }
                } else {
                    throw new \Exception('Unexpected format for keywords string');
                }

                if ($keywordsArray === null || empty($keywordsArray)) {
                    throw new \Exception('Failed to parse keywords array');
                }

                $this->info("  - Original: {$keywordsString}");
                $this->info("  - Parsed to array with " . count($keywordsArray) . " keywords");

                if (!$isDryRun) {
                    // Update the database record with the proper JSON array
                    DB::table('campaigns')
                        ->where('id', $campaign->id)
                        ->update(['keywords' => json_encode($keywordsArray)]);

                    $this->info("  ✅ Fixed");
                } else {
                    $this->info("  ✅ Would fix (dry-run mode)");
                }

                $fixed++;
            } catch (\Exception $e) {
                $this->error("  ❌ Error processing campaign {$campaign->id}: {$e->getMessage()}");
                $errors++;
            }

            $this->info('');
        }

        $this->info("Summary:");
        $this->info("- Campaigns processed: " . count($problematicCampaigns));
        $this->info("- Successfully " . ($isDryRun ? "would be " : "") . "fixed: {$fixed}");

        if ($errors > 0) {
            $this->info("- Errors encountered: {$errors}");
        }

        if ($isDryRun) {
            $this->info("\n🔄 Run without --dry-run to apply the fixes");
        } else {
            $this->info("\n✅ Campaign keyword fixes completed!");
        }

        return 0;
    }
}
