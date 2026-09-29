<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureStoreManager
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user || !in_array($user->role, ['super_admin', 'merchant', 'store_manager'])) {
            abort(403, 'Unauthorized access to Store Manager Portal.');
        }

        return $next($request);
    }
}
