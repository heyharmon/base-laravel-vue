<?php

namespace App\Http\Controllers;

use Prism\Prism\ValueObjects\Messages\UserMessage;
use Prism\Prism\Schema\StringSchema;
use Prism\Prism\Schema\ObjectSchema;
use Prism\Prism\Schema\ArraySchema;
use Prism\Prism\Prism;
use Prism\Prism\Enums\ToolChoice;
use Prism\Prism\Enums\Provider;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\JsonResponse;
use App\Tools\SearchApiTool;
use App\Models\Organization;

// TODO: Can probably remove this controller once the GeneratePromptsJob job is implemented
// If we still need it, we can move the functionality into a shared action.
class PromptGeneratorController extends Controller
{
	public function generate(Organization $organization): JsonResponse
	{
		if (!$organization->terms) {
			return response()->json(['error' => 'Organization does not have any terms.'], 422);
		}

		try {
			$searchApiTool = new SearchApiTool();

			// Create an array of messages
			$messages = [
				new UserMessage("Here is a list of term terms: " . json_encode($organization->terms) . ". Your job is to generate a list of statements, questions, or prompts that a person would likely put into ChatGPT for these terms.
The prompts should elicit a response that mentions specific brands. So, pretend you are given the term term, \"car loan\". In that case, an example of an acceptable prompt is, \"Where can I get the best car loan?\" because ChatGPT is likely to respond to that prompt with a list of organizations that can provide a loan. On the other hand, a bad example is, \"Tell me about auto loans\", because that is likely to elicit a response that gives general information rather than recommending specific companies.
Also, remember to keep the prompts simple. Don't make assumptions about the intent behind the term.
Output your suggested prompt as plain text, without quotation marks, or any type of formatting.")
			];

			// Add location message conditionally if location is available
			if (isset($organization->location) && !empty($organization->location)) {
				$messages[] = new UserMessage("You also need to incorporate the brands location \"" . $organization->location . "\" in the prompt when appropriate.
So, again pretend you are given the term term, \"car loan\" and the location is \"" . $organization->location . "\". In that case, an example of an acceptable prompt is, \"Where in " . $organization->location . " can I get the best car loan?\" because ChatGPT is likely to respond to that prompt with a list of organizations in " . $organization->location . " that can provide a loan.");
			}

			$textResponse = Prism::text()
				->using(Provider::OpenAI, 'gpt-4o')
				->withMaxSteps(10)
				->withMessages($messages)
				->withTools([$searchApiTool])
				->withToolChoice(ToolChoice::Auto)
				->asText();

			$schema = new ObjectSchema(
				name: 'prompt_suggestions',
				description: 'AI prompt suggestions',
				properties: [
					new ArraySchema(
						name: 'prompts',
						description: 'List of AI prompt suggestions',
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
