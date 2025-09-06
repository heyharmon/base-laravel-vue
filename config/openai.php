<?php

return [
    'api_key' => env('OPENAI_API_KEY'),
    'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),

    'timeout' => env('OPENAI_TIMEOUT', 900),

    'models' => [
        'default' => 'gpt-5',
        'available' => ['gpt-5'],
    ],

    'flex_processing' => [
        'enabled' => env('OPENAI_FLEX_ENABLED', true),
        'timeout' => 900,
        'cost_reduction' => 0.5,
    ],

    'retry' => [
        'max_attempts' => env('OPENAI_MAX_RETRIES', 10),
        'base_delay' => 2,
        'max_delay' => 300,
        'exponential_base' => 2,
    ],

    'polling' => [
        'enabled' => true,
        'base_delay' => 5,
        'max_delay' => 60,
        'max_time' => 3600,
    ],

    'rate_limits' => [
        'requests_per_minute' => 500,
        'tokens_per_minute' => 150000,
    ],
];
