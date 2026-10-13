<?php

namespace App\Http\Middleware;

use App\Models\PlatformSetting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPlatformMaintenanceMode
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $maintenance = PlatformSetting::get('maintenance_mode', '0');

        if ($maintenance === '1') {
            $user = $request->user();

            // Super Admin can always access the platform
            if ($user && $user->role === 'super_admin') {
                return $next($request);
            }

            // Allow login and logout endpoints so super admin can log in
            if ($request->is('login') || $request->is('logout') || $request->is('up') || $request->is('health')) {
                return $next($request);
            }

            if ($request->expectsJson() || $request->header('X-Inertia')) {
                return response()->json([
                    'message' => 'The platform is currently undergoing scheduled maintenance. Please check back shortly.',
                ], 503);
            }

            abort(503, 'The platform is currently undergoing scheduled maintenance. Please check back shortly.');
        }

        return $next($request);
    }
}
