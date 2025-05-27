<?php

namespace App\Http\Controllers;

use App\Jobs\CheckKeywordInPastResponsesJob;
use App\Models\Keyword;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class KeywordCheckInPastResponsesController extends Controller
{
    /**
     * Process a newly created keyword to check for matches in past responses.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $keywordId
     * @return \Illuminate\Http\JsonResponse
     */
    public function processNewKeyword(Request $request, $keywordId): JsonResponse
    {
        $keyword = Keyword::findOrFail($keywordId);

        // Dispatch the job to check for the keyword in past responses
        CheckKeywordInPastResponsesJob::dispatch($keyword);

        return response()->json([
            'message' => 'Keyword processing job dispatched successfully',
            'keyword_id' => $keyword->id
        ]);
    }

    /**
     * Process all keywords for an organization to check for matches in past responses.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $organizationId
     * @return \Illuminate\Http\JsonResponse
     */
    // public function processOrganizationKeywords(Request $request, $organizationId): JsonResponse
    // {
    //     $keywords = Keyword::where('organization_id', $organizationId)->get();

    //     $count = 0;
    //     foreach ($keywords as $keyword) {
    //         CheckKeywordInPastResponsesJob::dispatch($keyword);
    //         $count++;
    //     }

    //     return response()->json([
    //         'message' => 'Processing jobs dispatched for ' . $count . ' keywords',
    //         'keywords_count' => $count
    //     ]);
    // }
}
