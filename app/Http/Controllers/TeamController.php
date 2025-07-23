<?php

namespace App\Http\Controllers;

use App\Mail\NewUserTeamInvitation;
use App\Mail\TeamInvitation;
use App\Models\InvitationToken;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class TeamController extends Controller
{
	/**
	 * Display a listing of the teams.
	 */
	public function index()
	{
		$user = Auth::user();

		// Get teams owned by the user
		$ownedTeams = Team::where('owner_id', $user->id)->get();

		// Get teams where user is a member with accepted invitation
		$joinedTeams = Team::whereHas('users', function ($query) use ($user) {
			$query->where('user_id', $user->id)
				->where('invitation_accepted', true);
		})->orderBy('name')->get();

		// Get pending team invitations
		// TODO: Move invitations to a dedicated controller
		$pendingInvitations = Team::whereHas('users', function ($query) use ($user) {
			$query->where('user_id', $user->id)
				->where('invitation_accepted', false);
		})->get();

		// Add counts for UI display
		$ownedTeams->each(function ($team) {
			$team->members_count = $team->members()->count();
			$team->pending_invitations_count = $team->pendingInvitations()->count();
		});

		$joinedTeams->each(function ($team) {
			$team->members_count = $team->members()->count();
		});

		return response()->json([
			'ownedTeams' => $ownedTeams,
			'joinedTeams' => $joinedTeams,
			'pendingInvitations' => $pendingInvitations,
		]);
	}

	/**
	 * Store a newly created team in storage.
	 */
        public function store(Request $request)
        {
                $validated = $request->validate([
                        'name' => ['required', 'string', 'max:255'],
                ]);

		$validated['owner_id'] = Auth::id();
                $team = Team::create($validated);

                // Create a default campaign for the team
                $team->campaigns()->create([
                        'name' => 'Default Campaign',
                        'is_default' => true,
                ]);

		// Add the owner as a team member with admin role
		$team->users()->attach(Auth::id(), [
			'role' => 'admin',
			'invitation_accepted' => true,
			'joined_at' => now(),
		]);

		// Switch to the newly created team
		$this->switchTeam($team);

		return response()->json($team, 201);
	}

	/**
	 * Display the specified team.
	 */
	public function show(Team $team)
	{
		// Check if user is a member of the team
		$user = Auth::user();
		$isMember = $team->users()->where('user_id', $user->id)->exists();

		if (!$isMember && $team->owner_id !== $user->id) {
			return response()->json(['message' => 'Unauthorized'], 403);
		}

		// Load the team owner relationship
		$team->load('owner');

		// Get members and pending invitations
		$members = $team->members;
		$pendingInvitations = $team->pendingInvitations;

		// Add invitation URLs and token information to pending invitations
		$pendingInvitations->each(function ($invitation) use ($team) {
			// Check if there's an invitation token for this user (including expired ones)
			$token = InvitationToken::where('email', $invitation->email)
				->where('team_id', $team->id)
				->first();

			if ($token) {
				$invitation->token_expires_at = $token->expires_at;
				$invitation->token_expired = $token->expires_at <= now();

				if (!$invitation->token_expired) {
					$invitation->invitation_url = url('/register?token=' . $token->token);
				} else {
					$invitation->invitation_url = null; // No URL for expired tokens
				}
			}
		});

		// Check if current user is owner or admin
		$isOwner = $team->owner_id === $user->id;
		$isAdmin = $members->contains(function ($member) use ($user) {
			return $member->id === $user->id && $member->pivot->role === 'admin';
		});

		return response()->json([
			'team' => $team,
			'members' => $members,
			'pendingInvitations' => $pendingInvitations,
			'isOwner' => $isOwner,
			'isAdmin' => $isAdmin,
		]);
	}

	/**
	 * Update the specified team in storage.
	 */
	public function update(Request $request, Team $team)
	{
		// Check if user is owner or admin
		$user = Auth::user();
		$isOwner = $team->owner_id === $user->id;
		$isAdmin = $team->members()->where('user_id', $user->id)->where('role', 'admin')->exists();

		if (!$isOwner && !$isAdmin) {
			return response()->json(['message' => 'Unauthorized'], 403);
		}

		$validated = $request->validate([
			'name' => ['required', 'string', 'max:255'],
		]);

		$team->update($validated);

		return response()->json($team);
	}

	/**
	 * Remove the specified team from storage.
	 */
	public function destroy(Team $team)
	{
		// Only the owner can delete a team
		if ($team->owner_id !== Auth::id()) {
			return response()->json(['message' => 'Unauthorized'], 403);
		}

		$team->delete();

		return response()->json(['message' => 'Team deleted successfully']);
	}

	/**
	 * Alias for destroy to support DELETE /teams/{team} via "delete" route if needed.
	 */
	public function delete(Team $team)
	{
		return $this->destroy($team);
	}

	/**
	 * Invite a user to the team.
	 */
	public function invite(Request $request, Team $team)
	{
		// Check if user is owner or admin
		$user = Auth::user();
		$isOwner = $team->owner_id === $user->id;
		$isAdmin = $team->members()->where('user_id', $user->id)->where('role', 'admin')->exists();

		if (!$isOwner && !$isAdmin) {
			return response()->json(['message' => 'Unauthorized'], 403);
		}

		$validated = $request->validate([
			'email' => ['required', 'email'],
			'role' => ['required', Rule::in(['member', 'admin'])],
		]);

		// Find or create a user with this email
		$user = User::firstOrCreate(
			['email' => $validated['email']],
			[
				'name' => explode('@', $validated['email'])[0], // Use part before @ as name
				'password' => Hash::make(Str::random(16)), // Random password
			]
		);

		// Check if user is already in the team
		if ($team->users()->where('user_id', $user->id)->exists()) {
			return response()->json(['message' => 'User is already a member of this team'], 422);
		}

		// Add user to team with pending invitation
		$team->users()->attach($user->id, [
			'role' => $validated['role'],
			'invitation_sent_at' => now(),
		]);

		// Check if this is a new user (just created) or existing user
		$wasRecentlyCreated = $user->wasRecentlyCreated;

		if ($wasRecentlyCreated) {
			// For new users, create a token and send registration invitation
			$token = Str::random(64);

			// Store the token with expiration
			InvitationToken::create([
				'email' => $user->email,
				'token' => $token,
				'team_id' => $team->id,
				'expires_at' => now()->addHours(168), // 7 days
			]);

			// Send email with registration link
			Mail::to($user->email)->send(new NewUserTeamInvitation(
				$team,
				$user,
				$validated['role'],
				$token
			));
		} else {
			// For existing users, send regular team invitation
			Mail::to($user->email)->send(new TeamInvitation(
				$team,
				$user,
				$validated['role']
			));
		}

		return response()->json(['message' => 'Invitation sent successfully']);
	}

	/**
	 * Accept a team invitation.
	 */
	public function acceptInvitation(Team $team)
	{
		$user = Auth::user();

		// Check if there's a pending invitation
		$invitation = DB::table('team_user')
			->where('team_id', $team->id)
			->where('user_id', $user->id)
			->where('invitation_accepted', false)
			->first();

		if (!$invitation) {
			return response()->json(['message' => 'Invalid invitation'], 404);
		}

		// Update the invitation status
		DB::table('team_user')
			->where('team_id', $team->id)
			->where('user_id', $user->id)
			->update([
				'invitation_accepted' => true,
				'joined_at' => now(),
			]);

		return response()->json(['message' => 'You have joined the team']);
	}

	/**
	 * Decline a team invitation.
	 */
	public function declineInvitation(Team $team)
	{
		$user = Auth::user();

		// Remove the user from the team
		DB::table('team_user')
			->where('team_id', $team->id)
			->where('user_id', $user->id)
			->delete();

		// Remove any invitation tokens for this user/team combination
		InvitationToken::where('email', $user->email)
			->where('team_id', $team->id)
			->delete();

		// Note: We don't delete the authenticated user when they decline an invitation
		// as they are actively using the system

		return response()->json(['message' => 'Invitation declined']);
	}

	/**
	 * Remove a member from the team.
	 */
	public function removeMember(Team $team, User $user)
	{
		// Check if current user is owner or admin
		$currentUser = Auth::user();
		$isOwner = $team->owner_id === $currentUser->id;
		$isAdmin = $team->members()->where('user_id', $currentUser->id)->where('role', 'admin')->exists();

		if (!$isOwner && !$isAdmin) {
			return response()->json(['message' => 'Unauthorized'], 403);
		}

		// Prevent removing the team owner
		if ($user->id === $team->owner_id) {
			return response()->json(['message' => 'Cannot remove the team owner'], 422);
		}

		// Remove the user from the team
		$team->users()->detach($user->id);

		// Remove any invitation tokens for this user/team combination
		InvitationToken::where('email', $user->email)
			->where('team_id', $team->id)
			->delete();

		// Check if the user has any other team memberships (accepted or pending)
		$hasOtherTeamMemberships = $user->teams()->exists();

		// If the user has no other team memberships, delete the user record
		if (!$hasOtherTeamMemberships) {
			$user->delete();
		}

		return response()->json(['message' => 'Member removed successfully']);
	}

	/**
	 * Update a member's role in the team.
	 */
	public function updateMemberRole(Request $request, Team $team, User $user)
	{
		// Check if current user is owner or admin
		$currentUser = Auth::user();
		$isOwner = $team->owner_id === $currentUser->id;
		$isAdmin = $team->members()->where('user_id', $currentUser->id)->where('role', 'admin')->exists();

		if (!$isOwner && !$isAdmin) {
			return response()->json(['message' => 'Unauthorized'], 403);
		}

		// Prevent updating the team owner's role
		if ($user->id === $team->owner_id) {
			return response()->json(['message' => 'Cannot change the team owner\'s role'], 422);
		}

		$validated = $request->validate([
			'role' => ['required', Rule::in(['member', 'admin'])],
		]);

		$team->users()->updateExistingPivot($user->id, [
			'role' => $validated['role'],
		]);

		return response()->json(['message' => 'Member role updated successfully']);
	}

	/**
	 * Switch the authenticated user's current team.
	 */
	public function switchTeam(Team $team)
	{
		$user = Auth::user();

		// Check if user is a member of the team
		$isMember = $team->members()->where('user_id', $user->id)->exists();
		$isOwner = $team->owner_id === $user->id;

		if (!$isMember && !$isOwner) {
			return response()->json(['message' => 'You are not a member of this team'], 403);
		}

		// Update the user's current team
		DB::table('users')
			->where('id', $user->id)
			->update(['current_team_id' => $team->id]);

		return response()->json([
			'message' => 'Current team updated successfully',
			'team' => $team
		]);
	}
}
