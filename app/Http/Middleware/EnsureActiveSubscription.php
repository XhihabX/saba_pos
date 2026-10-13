<?php

namespace App\Http\Middleware;

use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveSubscription
{
    /**
     * Routes exempt from subscription checks to prevent redirect loops.
     */
    protected array $exemptRoutes = [
        'subscription.expired',
        'merchant.subscription',
        'merchant.subscription.renew',
        'logout',
        'pending.approval',
        '2fa.setup',
        '2fa.enable',
        '2fa.challenge',
        '2fa.verify',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Super admins have universal platform access; users without a tenant are bypassed.
        if (! $user || $user->role === 'super_admin' || ! $user->tenant) {
            return $next($request);
        }

        $tenant = $user->tenant;
        $currentRoute = $request->route() ? $request->route()->getName() : null;

        // Exempt routes always pass freely
        if ($currentRoute && in_array($currentRoute, $this->exemptRoutes, true)) {
            return $next($request);
        }

        $status = $tenant->subscription_status;

        // 1. Pending Approval / Rejected States
        if ($status === 'pending_approval' || $status === 'rejected') {
            if ($this->isJsonOrMutation($request)) {
                return response()->json(['message' => 'Account is pending approval.'], 402);
            }

            return redirect()->route('pending.approval');
        }

        // 2. Suspended State:
        // Blocked immediately (HTML and JSON), no grace, no late sync, no read-only unless explicitly enabled.
        if ($status === 'suspended') {
            if ($tenant->allow_suspended_read_only && $this->isReadOnlyRequest($request, $currentRoute)) {
                return $next($request);
            }

            if ($this->isJsonOrMutation($request)) {
                return response()->json(['message' => 'Tenant subscription is suspended.'], 402);
            }

            return $this->redirectToBlockedPage($user, 'Tenant subscription is suspended. Please contact support.');
        }

        // 3. Active State:
        if ($status === 'active') {
            // Subscription is active and either unexpired or within the grace period:
            // POS and normal operations keep functioning.
            if (! $tenant->isGraceExpired()) {
                if ($currentRoute === 'pending.approval') {
                    return redirect()->route('merchant.dashboard');
                }

                return $next($request);
            }

            // --- AFTER GRACE PERIOD ---

            // Offline-Sync Exception:
            // Accept checkout ONLY if:
            // - It is an offline-queue sync (flag + idempotency key)
            // - Client time is before the end of grace
            // - Server receives it within 7 days after the end of grace
            if ($this->isEligibleLateOfflineSync($request, $tenant, $currentRoute)) {
                return $next($request);
            }

            // Read-Only Window:
            // Allows read-only GET requests on whitelisted route names for 90 days.
            if ($tenant->isReadOnlyAllowed() && $this->isReadOnlyRequest($request, $currentRoute)) {
                return $next($request);
            }

            // Everything else after grace period gets HTTP 402 (for JSON/AJAX/mutations) or redirect to expired page.
            if ($this->isJsonOrMutation($request)) {
                return response()->json([
                    'message' => 'Subscription expired. Please ask the store owner to renew.',
                ], 402);
            }

            return $this->redirectToBlockedPage($user, 'Your subscription has expired. Please renew your plan.');
        }

        // Fallback for any unknown status
        if ($this->isJsonOrMutation($request)) {
            return response()->json(['message' => 'Subscription inactive.'], 402);
        }

        return $this->redirectToBlockedPage($user, 'Your subscription is inactive. Please renew your plan.');
    }

    /**
     * Determine whether the request is a JSON API, AJAX, or state-mutating request.
     */
    protected function isJsonOrMutation(Request $request): bool
    {
        if (! $request->isMethodSafe()) {
            return true;
        }

        return $request->expectsJson()
            || $request->isJson()
            || $request->ajax()
            || $request->is('api/*')
            || $request->is('pos/products/search')
            || $request->is('pos/customers/search')
            || $request->is('pos/parked-orders*')
            || $request->is('pos/shift/status');
    }

    /**
     * Check if request matches a read-only route pattern and uses a safe HTTP method.
     */
    protected function isReadOnlyRequest(Request $request, ?string $currentRoute): bool
    {
        if (! $request->isMethodSafe()) {
            return false;
        }

        if (! $currentRoute) {
            return false;
        }

        $patterns = config('subscription.read_only_route_patterns', [
            'reports.*',
            'pdf.*',
            'pos.invoice.pdf',
            'vat.mushak63',
            'merchant.orders',
            'merchant.auditlogs',
            'manager.reports',
            'manager.auditlogs',
            'manager.shifts.z-report',
        ]);

        return Str::is($patterns, $currentRoute);
    }

    /**
     * Validate offline queue late-sync eligibility.
     */
    protected function isEligibleLateOfflineSync(Request $request, $tenant, ?string $currentRoute): bool
    {
        if ($currentRoute !== 'pos.checkout' && ! $request->is('pos/checkout')) {
            return false;
        }

        // Must have offline sync flag
        $isOffline = $request->boolean('is_offline_sync')
            || $request->input('is_offline_sync') === '1'
            || $request->input('is_offline_sync') === 1;

        if (! $isOffline) {
            return false;
        }

        // Must have idempotency key
        $idempotencyKey = $request->input('idempotency_key') ?? $request->input('client_uuid');
        if (empty($idempotencyKey)) {
            return false;
        }

        // Must have client time or created_at
        $rawClientTime = $request->input('client_time') ?? $request->input('created_at');
        if (empty($rawClientTime)) {
            return false;
        }

        try {
            $tz = config('subscription.timezone', 'Asia/Dhaka');
            $clientTime = Carbon::parse($rawClientTime)->setTimezone($tz);
        } catch (\Throwable $e) {
            return false;
        }

        // End of grace period boundary
        $graceDays = (int) ($tenant->grace_days ?? config('subscription.default_grace_days', 5));
        $graceEndsAt = $tenant->expiresAtInTz()->copy()->addDays($graceDays);

        // Client time MUST be before or equal to the end of grace
        if ($clientTime->greaterThan($graceEndsAt)) {
            return false;
        }

        // Server receive time must be within 7 days after the end of grace
        $lateSyncDays = (int) config('subscription.late_sync_days', 7);
        $lateSyncEndsAt = $graceEndsAt->copy()->addDays($lateSyncDays);
        $now = Carbon::now($tz);

        if ($now->greaterThan($lateSyncEndsAt)) {
            return false;
        }

        return true;
    }

    /**
     * Redirect blocked users to their designated page without redirect loops.
     */
    protected function redirectToBlockedPage($user, string $message): Response
    {
        if ($user->role === 'merchant') {
            return redirect()->route('merchant.subscription')->with('error', $message);
        }

        return redirect()->route('subscription.expired')->with('error', $message);
    }
}
