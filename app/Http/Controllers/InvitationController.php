<?php

namespace App\Http\Controllers;

use App\Models\InvitationToken;
use Illuminate\Http\Request;

class InvitationController extends Controller
{
    /**
     * Verify an invitation token.
     */
    public function verify(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
            'email' => 'required|string|email',
        ]);

        $token = InvitationToken::where('token', $request->token)
            ->where('email', $request->email)
            ->where('expires_at', '>', now())
            ->first();

        if (!$token) {
            return response()->json([
                'valid' => false,
                'message' => 'Invalid or expired invitation token'
            ], 422);
        }

        return response()->json([
            'valid' => true,
            'email' => $token->email,
            'team_id' => $token->team_id
        ]);
    }
}
