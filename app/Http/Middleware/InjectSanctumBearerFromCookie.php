<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InjectSanctumBearerFromCookie
{
    /**
     * Read SPA auth token from secure HttpOnly cookie and inject it as Bearer token.
     * This is a resilient fallback when session authentication is unavailable.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->bearerToken()) {
            $token = $request->cookie('spa_auth');

            if (is_string($token) && $token !== '') {
                $request->headers->set('Authorization', 'Bearer '.$token);
            }
        }

        return $next($request);
    }
}

