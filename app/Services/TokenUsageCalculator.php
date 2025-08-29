<?php

namespace App\Services;

class TokenUsageCalculator
{
    public function calculate(array $usage, string $provider, string $model): array
    {
        $config = config("llm.$provider.$model");
        if (!$config) {
            return ['cost' => 0, 'price' => 0];
        }

        $inputTokens = $usage['input_tokens'] ?? 0;
        $cachedTokens = data_get($usage, 'input_tokens_details.cached_tokens', 0);
        $outputTokens = $usage['output_tokens'] ?? 0;

        $costRates = $config['cost'];
        $priceRates = $config['price'];

        $cost = ($inputTokens - $cachedTokens) * $costRates['input']
            + $cachedTokens * $costRates['cached_input']
            + $outputTokens * $costRates['output'];

        $price = ($inputTokens - $cachedTokens) * $priceRates['input']
            + $cachedTokens * $priceRates['cached_input']
            + $outputTokens * $priceRates['output'];

        return ['cost' => $cost, 'price' => $price];
    }
}
