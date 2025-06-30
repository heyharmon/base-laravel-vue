<?php

namespace App\Http\Controllers;

use App\Models\OrganizationIndustry;
use Illuminate\Http\Request;

class OrganizationIndustryController extends Controller
{
    public function index()
    {
        $industries = OrganizationIndustry::orderBy('name')->get();
        
        return response()->json($industries);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:organization_industries,name'
        ]);

        $industry = OrganizationIndustry::create([
            'name' => $request->name
        ]);

        return response()->json($industry, 201);
    }
}