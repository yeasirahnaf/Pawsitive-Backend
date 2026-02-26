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
        $exceptions->shouldRenderJsonWhen(function ($request) {
            return $request->is('api/*') || $request->expectsJson();
        });

        // Helper to format consistent API errors
        $formatError = fn ($message, $status = 400, $errors = []) => response()->json(
            array_filter([
                'success' => false,
                'message' => $message,
                'errors'  => empty($errors) ? null : $errors,
            ], fn ($v) => $v !== null),
            $status
        );

        // Handle custom API exceptions
        $exceptions->render(fn (App\Exceptions\ApiException $e) => $e->render());

        // Handle Laravel validation exceptions
        $exceptions->render(fn (\Illuminate\Validation\ValidationException $e) => 
            $formatError($e->getMessage() ?: 'Validation failed.', 422, $e->errors())
        );

        // Handle authentication exceptions
        $exceptions->render(fn (\Illuminate\Auth\AuthenticationException $e) => 
            $formatError('Authentication required.', 401)
        );

        // Handle authorization exceptions
        $exceptions->render(fn (\Illuminate\Auth\Access\AuthorizationException $e) => 
            $formatError($e->getMessage() ?: 'Unauthorized action.', 403)
        );

        // Handle model not found exceptions
        $exceptions->render(function (\Illuminate\Database\Eloquent\ModelNotFoundException $e) use ($formatError) {
            $model = class_basename($e->getModel());
            return $formatError("{$model} not found.", 404);
        });

        // Handle method not allowed exceptions
        $exceptions->render(fn (\Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException $e) => 
            $formatError('Method not allowed.', 405)
        );

        // Handle not found exceptions (404)
        $exceptions->render(fn (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e) => 
            $formatError('Resource not found.', 404)
        );

        // Handle throttle exceptions (rate limiting)
        $exceptions->render(fn (\Illuminate\Http\Exceptions\ThrottleRequestsException $e) => 
            $formatError('Too many requests. Please slow down.', 429)
        );

        // Handle database query exceptions
        $exceptions->render(function (\Illuminate\Database\QueryException $e) use ($formatError) {
            $message = config('app.debug') ? $e->getMessage() : 'A database error occurred.';
            return $formatError($message, 500);
        });

        // Handle all other exceptions
        $exceptions->render(function (\Throwable $e) use ($formatError) {
            $statusCode = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 500;
            $message = config('app.debug') ? $e->getMessage() : 'An unexpected error occurred.';
            return $formatError($message, $statusCode);
        });
    })->create();

