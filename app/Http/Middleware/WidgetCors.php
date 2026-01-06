<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * CORS Middleware for Widget API routes
 *
 * Widget routes need to allow any origin because they are embedded
 * on third-party websites. They use API key authentication instead
 * of cookies, so we don't need supports_credentials.
 */
class WidgetCors
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Handle preflight OPTIONS request
        if ($request->isMethod('OPTIONS')) {
            return $this->handlePreflightRequest($request);
        }

        $response = $next($request);

        return $this->addCorsHeaders($request, $response);
    }

    /**
     * Handle preflight OPTIONS request
     */
    private function handlePreflightRequest(Request $request): Response
    {
        $response = response('', 204);
        return $this->addCorsHeaders($request, $response);
    }

    /**
     * Add CORS headers to response
     */
    private function addCorsHeaders(Request $request, Response $response): Response
    {
        $origin = $request->headers->get('Origin');

        // Allow any origin for widget routes, including null (file:// protocol)
        // Widget uses API key authentication, not cookies, so it's safe
        if ($origin) {
            $response->headers->set('Access-Control-Allow-Origin', $origin);
        } else {
            // Allow null origin (file:// protocol, standalone HTML files)
            $response->headers->set('Access-Control-Allow-Origin', '*');
        }

        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, X-Widget-Key, X-Requested-With');
        $response->headers->set('Access-Control-Max-Age', '86400'); // 24 hours

        // Don't set Access-Control-Allow-Credentials for widget routes
        // Widget uses API key auth, not cookies

        return $response;
    }
}
