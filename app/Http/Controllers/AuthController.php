<?php

namespace App\Http\Controllers;

use App\Mail\SuperAdminNewUserNotification;
use App\Models\InvitationToken;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
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
			'email' => 'required|string|email:rfc,strict|max:255|unique:users',
			'password' => 'required|string|min:8|confirmed',
		]);

		$user = User::create([
			'name' => $request->name,
			'email' => $request->email,
			'password' => Hash::make($request->password),
		]);

		// Send notification to super admins in production environment
		if (app()->environment('production')) {
			$this->notifySuperAdmins($user);
		}

		$token = $user->createToken('auth_token')->plainTextToken;

		return response()->json([
			'user' => $user,
			'token' => $token
		], 201);
	}

	public function login(Request $request)
	{
		$request->validate([
			'email' => 'required|string|email:rfc,strict',
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

		// Set current team if not already set
		if (!$user->current_team_id) {
			// Try to find a team where the user is an owner
			$ownedTeam = Team::where('owner_id', $user->id)->first();

			if ($ownedTeam) {
				$user->current_team_id = $ownedTeam->id;
				$user->save();
			} else {
				// Try to find a team where the user is a member
				$memberTeam = $user->joinedTeams()->first();

				if ($memberTeam) {
					$user->current_team_id = $memberTeam->id;
					$user->save();
				}
			}
		}

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

	/**
	 * Register a new user with an invitation token.
	 */
	protected function registerWithInvitation(Request $request)
	{
		$request->validate([
			'name' => 'required|string|max:255',
			'password' => 'required|string|min:8|confirmed',
			'token' => 'required|string',
			'email' => 'required|string|email:rfc,strict|max:255',
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

		// Set the user's current team to the invited team
		$user->current_team_id = $invitationToken->team_id;
		$user->save();

		// Generate auth token
		$token = $user->createToken('auth_token')->plainTextToken;

		return response()->json([
			'user' => $user,
			'token' => $token
		], 201);
	}

	/**
	 * Send notification to all super admins about new user registration.
	 *
	 * @param User $newUser
	 * @return void
	 */
	protected function notifySuperAdmins(User $newUser)
	{
		// Get all super admin users
		$superAdmins = User::where('is_super_admin', true)->get();

		// Send email to each super admin
		foreach ($superAdmins as $superAdmin) {
			Mail::to($superAdmin->email)->send(new SuperAdminNewUserNotification($newUser));
		}
	}
}
