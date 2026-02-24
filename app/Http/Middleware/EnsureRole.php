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
        if (! $request->user() || ! $request->user()->tokenCan("role:{$role}")) {
            return response()->json([
                'success' => false,
                'error'   => [
                    'code'    => 'FORBIDDEN',
                    'message' => 'You do not have permission to perform this action.',
                ],
            ], 403);
        }

        return $next($request);
    }
}
