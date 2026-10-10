<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class ThrottleFailedPinAttempts
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $userId = $request->user()?->id ?? 'guest';
        $ip = $request->ip() ?? '127.0.0.1';
        $key = "pos-pin-failed:{$userId}:{$ip}";

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);

            return response()->json([
                'success' => false,
                'message' => "Too many failed PIN attempts. Lockout in effect for {$seconds} seconds.",
            ], 429)->header('Retry-After', (string) $seconds);
        }

        $response = $next($request);

        if ($response->getStatusCode() === 403) {
            RateLimiter::hit($key, 60);
        } elseif ($response->isSuccessful()) {
            RateLimiter::clear($key);
        }

        return $response;
    }
}
