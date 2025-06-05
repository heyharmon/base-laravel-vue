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

class TermGeneratorController extends Controller
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
				->withMessages([new UserMessage("I'm going to give you a website domain: {$domain}. Your job is to tell me the brand names associated with the domain. I want you to thoroughly search for the primary brand name as well as any alternate names, such as abbreviations, acronyms, nicknames, shortened names, etc and return them in a list.")])
				->withTools([$searchApiTool])
				->withToolChoice(ToolChoice::Auto)
				->asText();

			$schema = new ObjectSchema(
				name: 'domain_keywords',
				description: 'Keywords and brand names associated with a website domain',
				properties: [
					new ArraySchema(
						name: 'keywords',
						description: 'List of brand names and variations associated with the domain',
						items: new StringSchema(
							name: 'keyword',
							description: 'A brand name or variation'
						)
					)
				],
				requiredFields: ['keywords']
			);

			$response = Prism::structured()
				->using(Provider::OpenAI, 'gpt-4o')
				->withSchema($schema)
				->withPrompt('Here is a list of brand names associated with my brand, please return them as an array of keywords: ' . $textResponse->text)
				->asStructured();

			$result = $response->structured;

			// Add domain to keywords if not already included
			$keywords = $result['keywords'];
			if (!in_array($domain, $keywords)) {
				$keywords[] = $domain;
			}

			return response()->json($keywords);
		} catch (\Exception $e) {
			return response()->json(['error' => $e->getMessage()], 500);
		}
	}
}
