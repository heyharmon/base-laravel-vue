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
}
