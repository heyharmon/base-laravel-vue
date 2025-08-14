<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DebugFailedJobsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'jobs:debug-failed {--limit=10 : Number of failed jobs to show}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Debug failed jobs and show detailed information about failures';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $limit = (int) $this->option('limit');

        $this->info('=== Queue Status ===');
        $this->showQueueStatus();

        $this->info('=== Recent Failed Jobs ===');
        $this->showFailedJobs($limit);

        $this->info('=== RunPromptJob Specific Analysis ===');
        $this->analyzeRunPromptJobs();

        return 0;
    }

    /**
     * Show current queue status.
     */
    private function showQueueStatus(): void
    {
        try {
            // Show pending jobs
            $pendingJobs = DB::table('jobs')->count();
            $this->line("Pending jobs in queue: {$pendingJobs}");

            // Show failed jobs
            $failedJobs = DB::table('failed_jobs')->count();
            $this->line("Total failed jobs: {$failedJobs}");

            // Show jobs by queue
            $jobsByQueue = DB::table('jobs')
                ->select('queue', DB::raw('count(*) as count'))
                ->groupBy('queue')
                ->get();

            if ($jobsByQueue->isNotEmpty()) {
                $this->line('Jobs by queue:');
                foreach ($jobsByQueue as $queue) {
                    $this->line("  {$queue->queue}: {$queue->count}");
                }
            }
        } catch (\Exception $e) {
            $this->error('Error checking queue status: ' . $e->getMessage());
        }
    }

    /**
     * Show recent failed jobs.
     */
    private function showFailedJobs(int $limit): void
    {
        try {
            $failedJobs = DB::table('failed_jobs')
                ->orderBy('failed_at', 'desc')
                ->limit($limit)
                ->get();

            if ($failedJobs->isEmpty()) {
                $this->line('No failed jobs found.');
                return;
            }

            $headers = ['ID', 'Connection', 'Queue', 'Class', 'Failed At', 'Exception'];
            $rows = [];

            foreach ($failedJobs as $job) {
                $payload = json_decode($job->payload, true);
                $jobClass = $payload['displayName'] ?? 'Unknown';

                // Extract exception message (first 100 chars)
                $exception = substr($job->exception, 0, 100) . (strlen($job->exception) > 100 ? '...' : '');

                $rows[] = [
                    $job->id,
                    $job->connection,
                    $job->queue,
                    $jobClass,
                    $job->failed_at,
                    $exception
                ];
            }

            $this->table($headers, $rows);
        } catch (\Exception $e) {
            $this->error('Error retrieving failed jobs: ' . $e->getMessage());
        }
    }

    /**
     * Analyze RunPromptJob specific failures.
     */
    private function analyzeRunPromptJobs(): void
    {
        try {
            // Get RunPromptJob failures
            $runPromptFailures = DB::table('failed_jobs')
                ->where('payload', 'like', '%RunPromptJob%')
                ->orderBy('failed_at', 'desc')
                ->limit(5)
                ->get();

            if ($runPromptFailures->isEmpty()) {
                $this->line('No RunPromptJob failures found.');
                return;
            }

            foreach ($runPromptFailures as $failure) {
                $this->line('');
                $this->line("=== RunPromptJob Failure #{$failure->id} ===");
                $this->line("Failed at: {$failure->failed_at}");

                // Try to extract more details from payload
                $payload = json_decode($failure->payload, true);
                if ($payload) {
                    $this->line("Job attempts: " . ($payload['maxTries'] ?? 'unknown'));
                    $this->line("Timeout: " . ($payload['timeout'] ?? 'unknown'));

                    // Try to extract prompt info if available
                    if (isset($payload['data'])) {
                        $data = unserialize($payload['data']);
                        if (is_object($data) && property_exists($data, 'prompt')) {
                            $this->line("Prompt ID: " . ($data->prompt->id ?? 'unknown'));
                        }
                    }
                }

                // Show exception details
                $this->line("Exception:");
                $lines = explode("\n", $failure->exception);
                foreach (array_slice($lines, 0, 5) as $line) {
                    $this->line("  " . trim($line));
                }
                if (count($lines) > 5) {
                    $this->line("  ... (truncated)");
                }
            }

            // Show common failure patterns
            $this->line('');
            $this->line('=== Common Failure Patterns ===');

            $exceptionPatterns = DB::table('failed_jobs')
                ->where('payload', 'like', '%RunPromptJob%')
                ->get()
                ->groupBy(function ($job) {
                    // Extract first line of exception as pattern
                    $firstLine = explode("\n", $job->exception)[0] ?? '';
                    return substr($firstLine, 0, 100);
                })
                ->map(function ($group) {
                    return count($group);
                })
                ->sortDesc();

            foreach ($exceptionPatterns->take(3) as $pattern => $count) {
                $this->line("  [{$count}x] {$pattern}");
            }
        } catch (\Exception $e) {
            $this->error('Error analyzing RunPromptJob failures: ' . $e->getMessage());
        }
    }
}
