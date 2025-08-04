<?php

namespace App\Http\Controllers;

use App\Models\Account;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AccountController extends Controller
{
    public function index()
    {
        $accounts = Auth::user()->accounts()->withCount('transactions')->get();

        return response()->json($accounts);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'provider' => 'required|string|max:255',
        ]);

        $account = Auth::user()->accounts()->create($validated);

        return response()->json($account, 201);
    }

    public function show(Account $account)
    {
        $account->load(['transactions' => function ($query) {
            $query->latest('date')->limit(10);
        }]);

        return response()->json($account);
    }

    public function update(Request $request, Account $account)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'provider' => 'required|string|max:255',
        ]);

        $account->update($validated);

        return response()->json($account);
    }

    public function destroy(Account $account)
    {
        $account->delete();

        return response()->json(['message' => 'Account deleted successfully']);
    }
}
