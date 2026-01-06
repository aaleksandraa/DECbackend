<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Check if this is a widget route - allow embedding
        $isWidgetRoute = $request->is('widget/*') || $request->is('api/v1/widget/*');

        // Debug logging
        \Log::info('SecurityHeaders Middleware', [
            'path' => $request->path(),
            'isWidgetRoute' => $isWidgetRoute,
            'url' => $request->fullUrl(),
        ]);

        // Content Security Policy
        if (!$isWidgetRoute) {
            $response->headers->set('Content-Security-Policy',
                "default-src 'self'; " .
                "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://maps.googleapis.com https://www.googletagmanager.com; " .
                "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; " .
                "img-src 'self' data: https: blob:; " .
                "font-src 'self' data: https://fonts.gstatic.com; " .
                "connect-src 'self' https://maps.googleapis.com; " .
                "frame-src 'self' https://www.google.com; " .
                "object-src 'none'; " .
                "base-uri 'self'; " .
                "form-action 'self';"
            );
        } else {
            // More permissive CSP for widget embedding
            $response->headers->set('Content-Security-Policy',
                "default-src 'self'; " .
                "script-src 'self' 'unsafe-inline' 'unsafe-eval'; " .
                "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; " .
                "img-src 'self' data: https: blob:; " .
                "font-src 'self' data: https://fonts.gstatic.com; " .
                "connect-src 'self'; " .
                "frame-ancestors *; " . // Allow embedding from any domain
                "object-src 'none';"
            );
        }

        // Prevent MIME type sniffing
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // Prevent clickjacking - EXCEPT for widget routes
        if (!$isWidgetRoute) {
            $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        } else {
            // For widget routes, EXPLICITLY REMOVE X-Frame-Options to allow embedding
            $response->headers->remove('X-Frame-Options');
        }

        // XSS Protection (legacy browsers)
        $response->headers->set('X-XSS-Protection', '1; mode=block');

        // Referrer Policy
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Permissions Policy (formerly Feature Policy)
        $response->headers->set('Permissions-Policy',
            'geolocation=(self), ' .
            'microphone=(), ' .
            'camera=(), ' .
            'payment=(), ' .
            'usb=(), ' .
            'magnetometer=(), ' .
            'gyroscope=(), ' .
            'accelerometer=()'
        );

        return $response;
    }
}
