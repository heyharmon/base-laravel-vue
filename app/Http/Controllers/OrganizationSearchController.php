<?php

namespace App\Http\Controllers;

use App\Services\BrandFetchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrganizationSearchController extends Controller
{
    /**
     * The BrandFetch service instance.
     */
    protected BrandFetchService $brandFetchService;

    /**
     * Create a new controller instance.
     */
    public function __construct(BrandFetchService $brandFetchService)
    {
        $this->brandFetchService = $brandFetchService;
    }

    /**
     * Search for organizations by name.
     */
    public function search(Request $request): JsonResponse
    {
        $query = $request->input('query');

        if (empty($query)) {
            return response()->json(['results' => []], 200);
        }

        try {
            $results = $this->brandFetchService->searchBrands($query);
            return response()->json(['results' => $results], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to search brands', 'message' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Get detailed information about a specific brand.
     */
    public function brandDetails(Request $request): JsonResponse
    {
        $identifier = $request->input('identifier');
        
        if (empty($identifier)) {
            return response()->json(['error' => 'Identifier is required'], 422);
        }
        
        try {
            $details = $this->brandFetchService->getBrandDetails($identifier);
            return response()->json(['details' => $details], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch brand details', 'message' => $e->getMessage()], 500);
        }
    }
}
