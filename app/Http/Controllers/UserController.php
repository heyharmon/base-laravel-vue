<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class UserController extends Controller
{
    /**
     * Acknowledge the one-time warning about running individual prompts.
     */
    public function acknowledgeIndividualRunWarning(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        if (!$user->acknowledged_individual_run_warning) {
            $user->acknowledged_individual_run_warning = true;
            $user->save();
        }

        return response()->json($user);
    }
}

