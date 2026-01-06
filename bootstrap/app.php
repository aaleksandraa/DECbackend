<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\AuthenticationException;

return Application::configure()
    ->withProviders()
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'admin' => \App\Http\Middleware\AdminMiddleware::class,
            'throttle.custom' => \App\Http\Middleware\ThrottleRequests::class,
            'throttle.redis' => \App\Http\Middleware\ThrottleRequestsWithRedis::class,
            'widget.cors' => \App\Http\Middleware\WidgetCors::class,
            'bot.protection' => \App\Http\Middleware\BotProtection::class,
        ]);

        // Dodaj Security Headers middleware za sve API rute
        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);

        // Dodaj Sentry context middleware
        $middleware->append(\App\Http\Middleware\SentryContext::class);

        // NOTE: BotProtection is available as 'bot.protection' middleware alias
        // Apply it selectively on specific routes if needed, not globally

        // Dodaj stateful middleware za Sanctum SPA autentifikaciju
        $middleware->statefulApi();

        // Isključi API rute iz CSRF verifikacije
        $middleware->validateCsrfTokens(except: [
            'api/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Handle 404 errors
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Resurs nije pronađen.',
                    'error_code' => 'NOT_FOUND',
                ], 404);
            }
        });

        // Handle method not allowed errors
        $exceptions->render(function (MethodNotAllowedHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Metoda nije dozvoljena.',
                    'error_code' => 'METHOD_NOT_ALLOWED',
                ], 405);
            }
        });

        // Handle validation errors
        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validacija nije uspjela.',
                    'error_code' => 'VALIDATION_ERROR',
                    'errors' => $e->errors(),
                ], 422);
            }
        });

        // Handle authentication errors
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Niste autentificirani.',
                    'error_code' => 'UNAUTHENTICATED',
                ], 401);
            }
        });
    })
    ->create();
