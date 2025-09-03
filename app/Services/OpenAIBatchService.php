<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class OpenAIBatchService
{
    protected array $tools = [
        [
            'type' => 'web_search_preview',
        ],
    ];

    protected function apiKey(): string
    {
        return config('services.openai.api_key');
    }

    /**
     * Create a batch for the given prompts.
     *
     * @param \Illuminate\Support\Collection $prompts
     * @param int $count
     * @return string Batch ID
     */
    public function createBatch(Collection $prompts, int $count = 1): string
    {
        $lines = [];
        foreach ($prompts as $prompt) {
            for ($i = 0; $i < $count; $i++) {
                $lines[] = json_encode([
                    'custom_id' => 'prompt_' . $prompt->id . '_run_' . $i,
                    'method' => 'POST',
                    'url' => '/v1/responses',
                    'body' => [
                        'model' => 'gpt-5',
                        'input' => $prompt->content,
                        'tools' => $this->tools,
                        'tool_choice' => 'auto',
                        'store' => true,
                    ],
                ]);
            }
        }

        $tempPath = 'openai-batch-' . Str::uuid() . '.jsonl';
        Storage::disk('local')->put($tempPath, implode("\n", $lines));
        $filePath = Storage::disk('local')->path($tempPath);

        $upload = Http::withToken($this->apiKey())
            ->attach('file', fopen($filePath, 'r'), 'batch.jsonl')
            ->post('https://api.openai.com/v1/files', [
                'purpose' => 'batch',
            ])->json();

        Storage::disk('local')->delete($tempPath);

        $batch = Http::withToken($this->apiKey())
            ->post('https://api.openai.com/v1/batches', [
                'input_file_id' => $upload['id'] ?? null,
                'endpoint' => '/v1/responses',
                'completion_window' => '24h',
            ])->json();

        return $batch['id'] ?? '';
    }

    /**
     * Retrieve batch details.
     */
    public function retrieveBatch(string $batchId): array
    {
        return Http::withToken($this->apiKey())
            ->get("https://api.openai.com/v1/batches/{$batchId}")
            ->json();
    }

    /**
     * Download and parse batch output file.
     *
     * @return array<int, array>
     */
    public function downloadBatchOutput(string $fileId): array
    {
        $content = Http::withToken($this->apiKey())
            ->get("https://api.openai.com/v1/files/{$fileId}/content")
            ->body();

        $lines = array_filter(explode("\n", trim($content)));
        return array_map(fn ($line) => json_decode($line, true), $lines);
    }
}
