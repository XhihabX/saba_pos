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
            $status = $user->tenant->subscription_status;
            $currentRoute = $request->route() ? $request->route()->getName() : null;

            if ($status === 'pending_approval' || $status === 'rejected') {
                if ($currentRoute !== 'pending.approval' && $currentRoute !== 'logout') {
                    return redirect()->route('pending.approval');
                }
            } elseif ($status === 'suspended') {
                if ($currentRoute !== 'merchant.subscription' && $currentRoute !== 'logout') {
                    return redirect()->route('merchant.subscription')->with('error', 'Your tenant subscription is suspended. Please renew your plan.');
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
