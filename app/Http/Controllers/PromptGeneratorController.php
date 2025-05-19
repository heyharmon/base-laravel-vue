<?php

namespace App\Http\Controllers;

use Prism\Prism\ValueObjects\Messages\UserMessage;
use Prism\Prism\Schema\StringSchema;
use Prism\Prism\Schema\ObjectSchema;
use Prism\Prism\Schema\ArraySchema;
use Prism\Prism\Prism;
use Prism\Prism\Enums\ToolChoice;
use Prism\Prism\Enums\Provider;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Tools\SearchApiTool;

class PromptGeneratorController extends Controller
{
    public function generate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'domain' => 'required|string',
        ]);

        $domain = $validated['domain'];
        
        try {
            $searchApiTool = new SearchApiTool();

            $textResponse = Prism::text()
                ->using(Provider::OpenAI, 'gpt-4o')
                ->withMaxSteps(10)
                ->withMessages([new UserMessage("I have an analytics tool that shows when a brand shows up in AI chats. It works by giving prompts to AI and then tracking how often the brand shows up in the AI responses. 

So, I'm going to give you a website domain: {$domain}. Your job is to generate 10 prompts for AI that are highly relevant to the brand.

Imagine that you are a marketer for the brand. As such, you want your brand to be found when people are chatting with AI. So, think about what sort of prompts people would ask AI that you would want your brand mentioned in when the AI responds. Which prompts would you be disappointed to discover that your brand didn't show up in the response? Those are the prompts that I want you to tell me. 

Using SEO-type terminology, consider both fathead and long-tail types of prompts. Depending on the brand, sometimes prompts should be local by adding a city or state name to the prompt. Be wise when considering the prompts you suggest.

Research the products offered by the brand and consider prompts that relate directly to their products.")])
                ->withTools([$searchApiTool])
                ->withToolChoice(ToolChoice::Auto)
                ->asText();
            
            $schema = new ObjectSchema(
                name: 'prompt_suggestions',
                description: 'AI prompt suggestions related to a brand',
                properties: [
                    new ArraySchema(
                        name: 'prompts',
                        description: 'List of AI prompt suggestions related to the brand',
                        items: new StringSchema(
                            name: 'prompt',
                            description: 'A suggested AI prompt'
                        )
                    )
                ],
                requiredFields: ['prompts']
            );
            
            $response = Prism::structured()
                ->using(Provider::OpenAI, 'gpt-4o')
                ->withSchema($schema)
                ->withPrompt('Here is a list of prompts for my brand, please return them as an array: ' . $textResponse->text)
                ->asStructured();
                
            $result = $response->structured;
            
            return response()->json($result['prompts']);
            
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
