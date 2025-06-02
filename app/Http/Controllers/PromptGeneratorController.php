<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Tools\SearchApiTool;
use Illuminate\Http\JsonResponse;
use Prism\Prism\Prism;
use Prism\Prism\Enums\Provider;
use Prism\Prism\Enums\ToolChoice;
use Prism\Prism\Schema\ArraySchema;
use Prism\Prism\Schema\ObjectSchema;
use Prism\Prism\Schema\StringSchema;
use Prism\Prism\ValueObjects\Messages\UserMessage;

// TODO: Can probably remove this controller once the GeneratePromptsJob job is implemented
// If we still need it, we can move the functionality into a shared action.
class PromptGeneratorController extends Controller
{
	public function generate(Organization $organization): JsonResponse
	{
		if (!$organization->website) {
			return response()->json(['error' => 'Organization does not have a website domain.'], 422);
		}

		$domain = $organization->website;

		try {
			$searchApiTool = new SearchApiTool();

			$textResponse = Prism::text()
				->using(Provider::OpenAI, 'gpt-4o')
				->withMaxSteps(10)
				->withMessages([new UserMessage("...")])
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
