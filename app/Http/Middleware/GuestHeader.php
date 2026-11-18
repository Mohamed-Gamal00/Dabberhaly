<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guest API requests identify the guest with the `guest-id` header.
 * The header is required: without it the guest cannot be resolved,
 * so the request is rejected instead of acting on an unknown guest.
 */
class GuestHeader
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->header('guest-id')) {
            return response()->json([
                'status_code' => 401,
                'message' => 'Guest ID header is required.',
            ], 401);
        }

        return $next($request);
    }
}
