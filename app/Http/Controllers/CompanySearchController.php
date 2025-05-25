<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\OrganizationSearchService;

class CompanySearchController extends Controller
{
    public function __construct(protected OrganizationSearchService $service)
    {
    }

    public function index(Request $request)
    {
        $validated = $request->validate([
            'term' => 'required|string',
            'size' => 'sometimes|integer',
        ]);

        $results = $this->service->search(
            $validated['term'],
            $validated['size'] ?? 10
        );

        return response()->json($results);
    }
}

