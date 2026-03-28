<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie', 'login', 'logout', 'register'],

    'allowed_methods' => ['*'],

    // IMPORTANT: When supports_credentials is true, cannot use '*'
    // Must specify exact origins for authenticated routes
    // Widget routes use pattern matching below
    'allowed_origins' => array_values(array_unique(array_filter(array_merge(
        [
            'http://localhost:3000',
            'http://localhost:5173',
            'http://localhost:5174',
            'http://localhost:5175',
        ],
        (function () {
            $frontendUrl = env('FRONTEND_URL');
            if (!$frontendUrl) {
                return [];
            }

            $host = parse_url($frontendUrl, PHP_URL_HOST);
            $scheme = parse_url($frontendUrl, PHP_URL_SCHEME) ?: 'https';

            if (!$host) {
                return [];
            }

            return array_values(array_unique(array_filter([
                "{$scheme}://{$host}",
                "{$scheme}://www.".ltrim($host, 'www.'),
                "{$scheme}://".ltrim($host, 'www.'),
            ])));
        })(),
        array_map('trim', explode(',', (string) env('CORS_ALLOWED_ORIGINS', '')))
    )))),

    // Widget routes handle CORS separately via App\Http\Middleware\WidgetCors.
    // Keep global API CORS strict for cookie/session authentication.
    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];
