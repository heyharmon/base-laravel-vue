<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;

class TeamPasswordResetController extends Controller
{
	/**
	 * Generate a password reset URL for a team member
	 */
	public function generateResetUrl(Request $request, Team $team, User $user)
	{
		// Check if the current user has permission to generate reset URLs
		$currentUser = $request->user();
		$isOwner = $team->owner_id === $currentUser->id;
		$isAdmin = $team->users()
			->where('user_id', $currentUser->id)
			->wherePivot('role', 'admin')
			->exists();

		if (!$isOwner && !$isAdmin) {
			return response()->json(['message' => 'Unauthorized'], 403);
		}

		// Check if the user is a member of the team
		if (!$team->users()->where('user_id', $user->id)->exists()) {
			return response()->json(['message' => 'User is not a member of this team'], 404);
		}

		// Generate a token
		$token = Str::random(64);

		// Store the token in the password_reset_tokens table
		DB::table('password_reset_tokens')->updateOrInsert(
			['email' => $user->email],
			[
				'token' => $token,
				'created_at' => Carbon::now()
			]
		);

		// Create the reset URL
		$resetUrl = url('/reset-password') . '?token=' . $token . '&email=' . urlencode($user->email);

		return response()->json([
			'reset_url' => $resetUrl,
			'expires_at' => Carbon::now()->addMinutes(60)->toIso8601String()
		]);
	}
}
