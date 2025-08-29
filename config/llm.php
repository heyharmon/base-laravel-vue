<?php

return [
    'openai' => [
        'gpt-4o' => [
            'cost' => [
                'input' => 2.50e-6,
                'cached_input' => 1.25e-6,
                'output' => 10.00e-6,
            ],
            'price' => [
                'input' => env('PRICE_GPT4O_INPUT', 5e-6),
                'cached_input' => env('PRICE_GPT4O_CACHED_INPUT', 2.5e-6),
                'output' => env('PRICE_GPT4O_OUTPUT', 20.00e-6),
            ],
        ],
        'gpt-5' => [
            'cost' => [
                'input' => 1.25e-6,
                'cached_input' => 0.125e-6,
                'output' => 10.00e-6,
            ],
            'price' => [
                'input' => env('PRICE_GPT5_INPUT', 1.25e-6),
                'cached_input' => env('PRICE_GPT5_CACHED_INPUT', 0.125e-6),
                'output' => env('PRICE_GPT5_OUTPUT', 10.00e-6),
            ],
        ],
    ],
];
