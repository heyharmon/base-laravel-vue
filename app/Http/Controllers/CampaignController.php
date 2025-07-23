<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Team;
use App\Models\Campaign;

class CampaignController extends Controller
{
    public function index(Team $team): JsonResponse
    {
        return response()->json($team->campaigns()->get());
    }

    public function store(Request $request, Team $team): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $campaign = $team->campaigns()->create($data);

        return response()->json($campaign, 201);
    }
}
