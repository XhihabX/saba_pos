<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveSubscription
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->role !== 'super_admin' && $user->tenant) {
            $tenant = $user->tenant;
            $status = $tenant->subscription_status;
            $isExpired = $tenant->expires_at && \Carbon\Carbon::parse($tenant->expires_at)->isPast();
            $currentRoute = $request->route() ? $request->route()->getName() : null;

            if ($status === 'pending_approval' || $status === 'rejected') {
                if ($currentRoute !== 'pending.approval' && $currentRoute !== 'logout') {
                    return redirect()->route('pending.approval');
                }
            } elseif ($status === 'suspended' || $isExpired) {
                if ($currentRoute !== 'merchant.subscription' && $currentRoute !== 'logout') {
                    return redirect()->route('merchant.subscription')->with('error', 'Your tenant subscription has expired or is suspended. Please renew your plan.');
                }
            } elseif ($status === 'active') {
                if ($currentRoute === 'pending.approval') {
                    return redirect()->route('merchant.dashboard');
                }
            }
        }

        return $next($request);
    }
}
