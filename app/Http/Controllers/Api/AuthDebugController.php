<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthDebugController extends Controller
{
    /**
     * Lightweight auth diagnostics endpoint.
     * Safe for production troubleshooting (no secret values exposed).
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'authenticated' => (bool) $request->user(),
            'user_id' => $request->user()?->id,
            'guard' => auth()->getDefaultDriver(),
            'has_laravel_session_cookie' => $request->cookies->has(config('session.cookie')),
            'has_spa_auth_cookie' => $request->cookies->has('spa_auth'),
            'has_authorization_header' => $request->headers->has('Authorization'),
            'session_id_present' => $request->hasSession() ? (string) $request->session()->getId() !== '' : false,
            'host' => $request->getHost(),
            'secure' => $request->isSecure(),
        ]);
    }
}

