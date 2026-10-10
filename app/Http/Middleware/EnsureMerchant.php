<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMerchant
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        if (! in_array($user->role, ['super_admin', 'merchant'])) {
            return redirect()->route('login')->with('error', 'Unauthorized access to Merchant HQ Portal.');
        }

        return $next($request);
    }
}
