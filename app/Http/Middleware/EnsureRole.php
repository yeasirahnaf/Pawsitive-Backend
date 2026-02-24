<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    /**
     * Handle an incoming request.
     * Checks that the authenticated user's Sanctum token carries the required role ability.
     */
    public function handle(Request $request, Closure $next, string $role): Response
    {
        $user = $request->user();

        // 1. If not authenticated at all, throw AuthenticationException (handled globally as 401 "Login required")
        if (! $user) {
            throw new \Illuminate\Auth\AuthenticationException();
        }

        // 2. If authenticated, but missing the correct token ability OR database role, return 403
        if (! $user->tokenCan("role:{$role}") || $user->role !== $role) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to perform this action.'
            ], 403);
        }

        return $next($request);
    }
}
