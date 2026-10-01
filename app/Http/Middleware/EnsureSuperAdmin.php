<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSuperAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return redirect()->route('login');
        }

        if ($user->role !== 'super_admin') {
            return redirect()->route('login')->with('error', 'Unauthorized access to SaaS Super Admin Portal.');
        }

        return $next($request);
    }
}
