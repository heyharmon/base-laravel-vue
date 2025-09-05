<?php

namespace App\Http\Controllers;

use App\Models\Prompt;
use App\Models\Response;
use Illuminate\Http\JsonResponse;

class PromptResponsesController extends Controller
{
    public function index(Prompt $prompt): JsonResponse
    {
        // Get all responses for this prompt
        $responses = Response::where('prompt_id', $prompt->id)
            ->select(['id','provider','model','flex','provider_id','status','content','search','usage','poll_attempts','error_code','processing_error_message','created_at','updated_at'])
            ->latest()
            ->get();
        
        return response()->json($responses);
    }
}
