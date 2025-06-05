<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PulseAuthorization
{
	/**
	 * Handle an incoming request.
	 */
	public function handle(Request $request, Closure $next): Response
	{
		// Check for password in the environment
		$pulsePassword = env('PULSE_PASSWORD');

		if ($pulsePassword) {
			// Check for password in the request
			$providedPassword = $request->input('password');

			// Store password in session if it matches
			if ($providedPassword === $pulsePassword) {
				$request->session()->put('pulse_authorized', true);
			}

			// Check if authorized in session
			if (!$request->session()->get('pulse_authorized', false)) {
				if ($request->expectsJson()) {
					return response()->json(['message' => 'Unauthorized'], 403);
				}

				return response()->view('pulse::auth', [], 403);
			}
		}

		return $next($request);
	}
}
