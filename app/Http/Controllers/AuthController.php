<?php

namespace App\Http\Controllers;

use App\Models\InvitationToken;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        // Check if there's a token in the request for invitation registration
        if ($request->has('token')) {
            return $this->registerWithInvitation($request);
        }
        
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        // Create a team for the user
        $team = Team::create([
            'name' => $user->name . ' Team',
            'owner_id' => $user->id,
        ]);

        // Add user to their team as admin
        $team->users()->attach($user->id, [
            'role' => 'admin',
            'invitation_accepted' => true,
            'joined_at' => now(),
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token
        ], 201);
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        // Revoke all existing tokens
        $user->tokens()->delete();

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully']);
    }

    public function user(Request $request)
    {
        return response()->json($request->user());
    }
    
    /**
     * Register a new user with an invitation token.
     */
    protected function registerWithInvitation(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'password' => 'required|string|min:8|confirmed',
            'token' => 'required|string',
            'email' => 'required|string|email|max:255',
        ]);
        
        // Find the invitation token
        $invitationToken = InvitationToken::where('token', $request->token)
            ->where('email', $request->email)
            ->where('expires_at', '>', now())
            ->first();
            
        if (!$invitationToken) {
            return response()->json(['message' => 'Invalid or expired invitation token'], 422);
        }
        
        // Find the user (should exist as it was created during invitation)
        $user = User::where('email', $request->email)->first();
        
        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }
        
        // Update user details
        $user->name = $request->name;
        $user->password = Hash::make($request->password);
        $user->save();
        
        // Accept the team invitation
        DB::table('team_user')
            ->where('team_id', $invitationToken->team_id)
            ->where('user_id', $user->id)
            ->update([
                'invitation_accepted' => true,
                'joined_at' => now(),
            ]);
            
        // Delete the used token
        $invitationToken->delete();
        
        // Generate auth token
        $token = $user->createToken('auth_token')->plainTextToken;
        
        return response()->json([
            'user' => $user,
            'token' => $token
        ], 201);
    }
}
