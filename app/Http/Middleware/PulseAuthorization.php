<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class PulseAuthorization
{
	/**
	 * Handle an incoming request.
	 */
	public function handle(Request $request, Closure $next): Response
	{
		// Try to authenticate with token if not already authenticated via session
		if (!Auth::check()) {
			// Check for API token in Authorization header
			if ($request->bearerToken()) {
				$user = Auth::guard('sanctum')->user();
				if ($user) {
					Auth::login($user);
				}
			}

			// Check for API token in query string (for direct browser access)
			if (!Auth::check() && $request->query('api_token')) {
				$token = $request->query('api_token');
				$user = \App\Models\User::whereHas('tokens', function ($query) use ($token) {
					$query->where('token', hash('sha256', $token));
				})->first();

				if ($user) {
					Auth::login($user);
				}
			}
		}

		// Check if the user can view Pulse
		if (Gate::denies('viewPulse')) {
			if ($request->expectsJson()) {
				return response()->json(['message' => 'Unauthorized'], 403);
			}

			abort(403, 'This action is unauthorized.');
		}

		return $next($request);
	}
}
