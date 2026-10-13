<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SecurityHeadersMiddleware;
use App\Http\Middleware\ThrottleFailedPinAttempts;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Inertia\Inertia;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');
        $middleware->trustHosts(at: fn () => array_values(array_filter([
            '^pos\.sababilling\.net$',
            '^sababilling\.net$',
            parse_url(config('app.url'), PHP_URL_HOST) ? '^'.preg_quote(parse_url(config('app.url'), PHP_URL_HOST)).'$' : null,
        ])), subdomains: false);
        $middleware->alias([
            'throttle.pin' => ThrottleFailedPinAttempts::class,
        ]);
        $middleware->web(append: [
            HandleInertiaRequests::class,
            SecurityHeadersMiddleware::class,
            \App\Http\Middleware\CheckPlatformMaintenanceMode::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->respond(function ($response, Throwable $e, Request $request) {
            if ($e instanceof \Symfony\Component\HttpFoundation\Exception\SuspiciousOperationException) {
                return response('Bad Request: Untrusted Host', 400);
            }
            if (in_array($response->getStatusCode(), [500, 503, 404, 403])) {
                if ($request->header('X-Inertia')) {
                    return Inertia::render('Error', [
                        'status' => $response->getStatusCode(),
                        'message' => $e->getMessage() ?: 'Requested resource was not found.',
                    ])->toResponse($request)->setStatusCode($response->getStatusCode());
                }
            }

            return $response;
        });
    })->create();
