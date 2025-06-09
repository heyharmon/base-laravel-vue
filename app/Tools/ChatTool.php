<?php

namespace App\Tools;

interface ChatTool
{
    /**
     * Unique name used by the LLM when calling this tool.
     */
    public function name(): string;

    /**
     * Definition array used by OpenAI tool calling.
     * Must return a properly formatted tool definition for the OpenAI Responses API.
     */
    public function definition(): array;

    /**
     * Execute the tool with the given arguments.
     * Should return a string or array that will be sent back to the model.
     */
    public function run(array $arguments);
}
