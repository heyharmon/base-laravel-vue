<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Services\OrganizationSearchService;

class OrganizationSearchController extends Controller
{
    public function __construct(private OrganizationSearchService $service)
    {
    }

    /**
     * Search for companies by name or website.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'query' => 'required|string|max:255',
            'size'  => 'sometimes|integer|min:1|max:25',
        ]);

        $results = $this->service->search(
            $validated['query'],
            $validated['size'] ?? 10
        );

        return response()->json($results);
    }
}

