<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(prepend: [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        ]);

        $middleware->alias([
            'verified' => \App\Http\Middleware\EnsureEmailIsVerified::class,
            'admin' => \App\Http\Middleware\EnsureUserIsAdmin::class,
            'rate_limit_ai' => \App\Http\Middleware\RateLimitAiRequests::class,
        ]);

        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Ensure API routes return JSON responses for authentication errors
        $exceptions->shouldRenderJsonWhen(function ($request, \Throwable $e) {
            // Return JSON for API routes (check if request expects JSON or is an API route)
            if ($request->expectsJson() || $request->is('api/*')) {
                return true;
            }
            return false;
        });
    })->create();
