<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Sentry\State\Scope;
use Symfony\Component\HttpFoundation\Response;

class SentryContext
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (app()->bound('sentry')) {
            \Sentry\configureScope(function (Scope $scope): void {
                if (Auth::check()) {
                    $user = Auth::user();

                    $scope->setUser([
                        'id' => $user->id,
                        'email' => $user->email,
                        'role' => $user->role,
                    ]);
                }

                // Add custom context
                $scope->setTag('environment', config('app.env'));
                $scope->setTag('server', gethostname());
            });
        }

        return $next($request);
    }
}
