<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class RateLimitAiRequests
{
    /**
     * Handle an incoming request.
     * Limits AI requests to 5 per minute per user
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $key = 'ai_requests:' . $user->id;

        if (RateLimiter::tooManyAttempts($key, 5)) {
            return response()->json([
                'message' => 'Too many AI requests. Please wait a minute before trying again.',
            ], 429);
        }

        RateLimiter::hit($key, 60); // 60 seconds = 1 minute

        return $next($request);
    }
}
