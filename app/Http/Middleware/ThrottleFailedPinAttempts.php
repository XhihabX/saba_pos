<?php

namespace App\Http\Middleware;

use App\Mail\SupervisorPinLockoutAlertMail;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class ThrottleFailedPinAttempts
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $userId = $user ? (string) $user->id : 'guest';
        $storeId = (int) (($user && $user->store_id) ? $user->store_id : $request->input('store_id', 1));

        $userLockKey = "pos-pin:user:lock:{$userId}:{$storeId}";
        $userAttemptsKey = "pos-pin:user:attempts:{$userId}:{$storeId}";
        $storeLockKey = "pos-pin:store:lock:{$storeId}";
        $storeAttemptsKey = "pos-pin:store:attempts:{$storeId}";

        // 1. Check if user is locked out (15 minutes lockout)
        if (RateLimiter::tooManyAttempts($userLockKey, 1)) {
            $seconds = RateLimiter::availableIn($userLockKey);

            return response()->json([
                'success' => false,
                'message' => "Too many failed PIN attempts. User locked out for {$seconds} seconds.",
            ], 429)->header('Retry-After', (string) $seconds);
        }

        // 2. Check if store is locked out (10 minutes lockout)
        if (RateLimiter::tooManyAttempts($storeLockKey, 1)) {
            $seconds = RateLimiter::availableIn($storeLockKey);

            return response()->json([
                'success' => false,
                'message' => "Store-wide PIN verification limit exceeded. Lockout in effect for {$seconds} seconds.",
            ], 429)->header('Retry-After', (string) $seconds);
        }

        $response = $next($request);

        // 3. Process failure / success
        if ($response->getStatusCode() === 403) {
            // User failure: 5 attempts within 10 minutes (600s)
            $userFailures = RateLimiter::hit($userAttemptsKey, 600);
            // Store failure: 20 attempts within 10 minutes (600s)
            $storeFailures = RateLimiter::hit($storeAttemptsKey, 600);

            // User lockout: 5 failures -> lock user for 15 minutes (900s) and email manager
            if ($userFailures >= 5) {
                RateLimiter::hit($userLockKey, 900);
                RateLimiter::clear($userAttemptsKey);

                if ($user instanceof User) {
                    $this->notifyManagerOfLockout($user, $storeId);
                }
            }

            // Store-wide cap: 20 failures -> lock store for 10 minutes (600s)
            if ($storeFailures >= 20) {
                RateLimiter::hit($storeLockKey, 600);
                RateLimiter::clear($storeAttemptsKey);
            }
        } elseif ($response->isSuccessful()) {
            RateLimiter::clear($userAttemptsKey);
        }

        return $response;
    }

    /**
     * Notify store manager or tenant owner of cashier PIN lockout.
     */
    protected function notifyManagerOfLockout(User $user, int $storeId): void
    {
        try {
            $manager = User::where('store_id', $storeId)
                ->where('role', 'store_manager')
                ->first();

            $owner = $user->tenant_id
                ? User::where('tenant_id', $user->tenant_id)->where('role', 'merchant')->first()
                : null;

            $recipientEmail = $user->email;
            if ($manager && ! empty($manager->email)) {
                $recipientEmail = $manager->email;
            } elseif ($owner && ! empty($owner->email)) {
                $recipientEmail = $owner->email;
            }

            if ($recipientEmail) {
                Mail::to($recipientEmail)->send(new SupervisorPinLockoutAlertMail($user, $storeId));
            }
        } catch (\Throwable $e) {
            Log::error("Failed sending supervisor PIN lockout email: {$e->getMessage()}");
        }
    }
}
