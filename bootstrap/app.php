<?php

use App\Http\Middleware\EnsureRole;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api/v1',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Force non-Redis throttle — phpredis/predis not installed locally
        $middleware->alias([
            'role'     => EnsureRole::class,
            'throttle' => ThrottleRequests::class,
        ]);

        // Global API throttle: 100 req/min per IP
        $middleware->throttleApi(100, 1);

        // Disable redirects for unauthenticated users so they don't hit "Route [login] not defined"
        // if they forget the Accept: application/json header.
        $middleware->redirectGuestsTo(fn () => null);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Always return JSON for /api/* routes — no more HTML error pages in Postman
        $exceptions->shouldRenderJsonWhen(function ($request, $e) {
            return $request->is('api/*') || $request->expectsJson();
        });

        // Custom Unauthenticated response
        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Login required'
                ], 401);
            }
        });
    })->create();

