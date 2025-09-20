<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class HorizonBasicAuthMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (app()->environment('local')) {
            return $next($request);
        }

        $authenticationHasPassed = false;

        $username = $request->getUser();
        $password = $request->getPassword();

        $configUsername = config('horizon.basic_auth.username');
        $configPassword = config('horizon.basic_auth.password');

        if ($username !== null && $password !== null && $configUsername && $configPassword) {
            $authenticationHasPassed = hash_equals($configUsername, $username)
                && hash_equals($configPassword, $password);
        }

        if ($authenticationHasPassed === false) {
            return response('Invalid credentials.', 401, ['WWW-Authenticate' => 'Basic']);
        }

        return $next($request);
    }
}
