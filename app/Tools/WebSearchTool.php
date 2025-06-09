<?php

namespace App\Tools;

class WebSearchTool implements ChatTool
{
    /**
     * Unique name used by the LLM when calling this tool.
     */
    public function name(): string
    {
        return 'web_search';
    }

    /**
     * Definition array used by OpenAI tool calling.
     * Must return a properly formatted tool definition for the OpenAI API.
     */
    public function definition(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => $this->name(),
                'description' => 'Search the web for content on the given query.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'query' => [
                            'type' => 'string',
                            'description' => 'The search query'
                        ]
                    ],
                    'required' => ['query']
                ]
            ]
        ];
    }

    /**
     * Execute the tool with the given arguments.
     * For web_search, this is handled by OpenAI directly so we don't need to implement it.
     * This method will never be called since OpenAI handles the web search internally.
     */
    public function run(array $arguments)
    {
        // This method should never be called since OpenAI handles web search internally
        return 'Web search is handled by OpenAI directly.';
    }
}
