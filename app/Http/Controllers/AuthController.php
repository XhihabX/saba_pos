<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class AuthController extends Controller
{
    public function showLogin()
    {
        return Inertia::render('Auth/Login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();
            $request->session()->forget('url.intended');
            $user = Auth::user();

            if ($user->role === 'super_admin') {
                return redirect('/super-admin/dashboard');
            } elseif ($user->role === 'merchant') {
                return redirect('/merchant/dashboard');
            } elseif ($user->role === 'store_manager') {
                return redirect('/manager/dashboard');
            } else {
                return redirect('/pos');
            }
        }

        return back()->withErrors([
            'email' => 'The provided credentials do not match our records.',
        ]);
    }

    public function switchRole($role)
    {
        $roleMap = [
            'super_admin' => 'admin@iotpos.com',
            'merchant' => 'merchant@iotpos.com',
            'store_manager' => 'manager@iotpos.com',
            'cashier' => 'cashier@iotpos.com',
        ];

        $email = $roleMap[$role] ?? 'merchant@iotpos.com';
        $user = \App\Models\User::where('email', $email)->first();

        if ($user) {
            Auth::login($user);
            request()->session()->regenerate();
            request()->session()->forget('url.intended');

            if ($user->role === 'super_admin') {
                return redirect('/super-admin/dashboard')->with('success', "Switched role to Super Admin SaaS Command Center.");
            } elseif ($user->role === 'merchant') {
                return redirect('/merchant/dashboard')->with('success', "Switched role to Merchant HQ Portal.");
            } elseif ($user->role === 'store_manager') {
                return redirect('/manager/dashboard')->with('success', "Switched role to Store Manager Portal.");
            } else {
                return redirect('/pos')->with('success', "Switched role to Cashier POS Workstation.");
            }
        }

        return redirect('/login')->with('error', 'Role account not found.');
    }

    public function showRegister(Request $request)
    {
        $plans = \App\Models\SaaSPlan::where('is_active', true)->get();
        return Inertia::render('Auth/Register', [
            'selectedPlan' => $request->query('plan', 'growth'),
            'plans' => $plans,
        ]);
    }

    public function showPendingApproval()
    {
        $user = Auth::user();
        $tenant = $user ? $user->tenant : null;

        return Inertia::render('Auth/PendingApproval', [
            'tenant' => $tenant,
        ]);
    }

    public function register(Request $request)
    {
        $validated = $request->validate([
            'business_name' => 'required|string|max:255',
            'owner_name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email|unique:tenants,email',
            'phone' => 'nullable|string',
            'password' => 'required|string|min:6|confirmed',
            'plan_name' => 'required|string',
            'payment_method' => 'required|string|in:bkash,nagad,rocket,bank',
            'sender_number' => 'required|string|min:11|max:15',
            'transaction_id' => 'required|string|max:100',
        ]);

        return \Illuminate\Support\Facades\DB::transaction(function () use ($validated) {
            $planPrices = [
                'Starter POS' => 1499.00,
                'Growth Multi-Store' => 3999.00,
                'Enterprise ERP' => 9999.00,
                'starter' => 1499.00,
                'growth' => 3999.00,
                'enterprise' => 9999.00,
            ];

            $dbPlan = \App\Models\SaaSPlan::where('name', 'LIKE', '%' . $validated['plan_name'] . '%')->first();
            $mrr = $dbPlan ? (float) $dbPlan->monthly_price : ($planPrices[$validated['plan_name']] ?? 3999.00);
            $code = 'TENANT-' . strtoupper(substr(uniqid(), -5));

            $tenant = \App\Models\Tenant::create([
                'name' => $validated['business_name'],
                'code' => $code,
                'email' => $validated['email'],
                'phone' => $validated['phone'] ?? null,
                'plan_name' => ucfirst($validated['plan_name']),
                'subscription_status' => 'pending_approval',
                'mrr_amount' => $mrr,
                'payment_method' => $validated['payment_method'],
                'sender_number' => $validated['sender_number'],
                'transaction_id' => $validated['transaction_id'],
                'expires_at' => null,
            ]);

            $store = \App\Models\Store::create([
                'tenant_id' => $tenant->id,
                'name' => $validated['business_name'] . ' Main Outlet',
                'code' => 'STORE-' . strtoupper(substr(uniqid(), -4)),
                'phone' => $validated['phone'] ?? null,
                'email' => $validated['email'],
                'currency_symbol' => '৳',
                'default_tax_rate' => 15.00,
                'is_active' => true,
            ]);

            $user = \App\Models\User::create([
                'tenant_id' => $tenant->id,
                'store_id' => $store->id,
                'name' => $validated['owner_name'],
                'email' => $validated['email'],
                'password' => \Illuminate\Support\Facades\Hash::make($validated['password']),
                'role' => 'merchant',
            ]);

            \App\Models\AuditLog::create([
                'tenant_id' => $tenant->id,
                'user_name' => $user->name,
                'action' => 'merchant_registered_pending_payment',
                'description' => "Merchant '{$tenant->name}' submitted registration with manual payment via {$validated['payment_method']} (TrxID: {$validated['transaction_id']}). Pending Super Admin verification.",
            ]);

            Auth::login($user);

            return redirect()->route('pending.approval')->with('success', 'Registration submitted successfully! Your account payment is currently under Super Admin verification.');
        });
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }
}
