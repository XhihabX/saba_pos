<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\PlatformSetting;
use App\Models\SaaSPlan;
use App\Models\Store;
use App\Models\SubscriptionPayment;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
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
        $user = User::where('email', $email)->first();

        if ($user) {
            Auth::login($user);
            request()->session()->regenerate();
            request()->session()->forget('url.intended');

            if ($user->role === 'super_admin') {
                return redirect('/super-admin/dashboard')->with('success', 'Switched role to Super Admin SaaS Command Center.');
            } elseif ($user->role === 'merchant') {
                return redirect('/merchant/dashboard')->with('success', 'Switched role to Merchant HQ Portal.');
            } elseif ($user->role === 'store_manager') {
                return redirect('/manager/dashboard')->with('success', 'Switched role to Store Manager Portal.');
            } else {
                return redirect('/pos')->with('success', 'Switched role to Cashier POS Workstation.');
            }
        }

        return redirect('/login')->with('error', 'Role account not found.');
    }

    public function showRegister(Request $request)
    {
        $plans = SaaSPlan::where('is_active', true)->get();
        $settings = PlatformSetting::getAll();

        return Inertia::render('Auth/Register', [
            'selectedPlan' => $request->query('plan', 'growth'),
            'plans' => $plans,
            'settings' => $settings,
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

    public function showSubscriptionExpired()
    {
        $user = Auth::user();
        $tenant = $user ? $user->tenant : null;

        return Inertia::render('Auth/SubscriptionExpired', [
            'tenant' => $tenant,
        ]);
    }

    public function register(Request $request)
    {
        // 1. Honeypot check
        if ($request->filled('website_hp')) {
            abort(422, 'Spam submission detected.');
        }

        // 2. Dynamic validation rules
        $rules = [
            'website_hp' => 'nullable|max:0',
            'business_name' => 'required|string|max:255',
            'owner_name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email|unique:tenants,email',
            'phone' => 'nullable|string',
            'password' => 'required|string|min:6|confirmed',
            'plan_name' => 'required|string',
            'payment_method' => 'required|string|in:bkash,nagad,rocket,bank',
            'transaction_id' => 'required|string|max:100',
            'proof_file' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:3072',
        ];

        if ($request->input('payment_method') === 'bank') {
            $rules['bank_name'] = 'required|string|max:100';
            $rules['account_holder'] = 'required|string|max:100';
            $rules['account_last4'] = 'required|string|regex:/^\d{4}$/';
            $rules['sender_number'] = 'nullable|string';
        } else {
            $rules['sender_number'] = 'required|string|min:11|max:15';
        }

        $validated = $request->validate($rules);

        // 3. Duplicate reference check
        $duplicate = Tenant::where('transaction_id', $validated['transaction_id'])->exists() ||
            SubscriptionPayment::where('reference', $validated['transaction_id'])->exists();

        if ($duplicate) {
            return redirect()->back()->withErrors([
                'transaction_id' => 'This payment reference / TrxID has already been submitted and cannot be reused.',
            ]);
        }

        $proofPath = null;
        if ($request->hasFile('proof_file')) {
            $proofPath = $request->file('proof_file')->store('subscription_proofs', 'local');
        }

        return DB::transaction(function () use ($validated, $proofPath) {
            $planPrices = [
                'Starter POS' => 1499.00,
                'Growth Multi-Store' => 3999.00,
                'Enterprise ERP' => 9999.00,
                'starter' => 1499.00,
                'growth' => 3999.00,
                'enterprise' => 9999.00,
            ];

            $dbPlan = SaaSPlan::where('name', 'LIKE', '%'.$validated['plan_name'].'%')->first();
            $mrr = $dbPlan ? (float) $dbPlan->monthly_price : ($planPrices[$validated['plan_name']] ?? 3999.00);
            $planId = $dbPlan ? $dbPlan->id : (SaaSPlan::first()?->id);
            $code = 'TENANT-'.strtoupper(substr(uniqid(), -5));

            $tenant = Tenant::create([
                'name' => $validated['business_name'],
                'code' => $code,
                'email' => $validated['email'],
                'phone' => $validated['phone'] ?? null,
                'plan_name' => ucfirst($validated['plan_name']),
                'subscription_status' => 'pending_approval',
                'mrr_amount' => $mrr,
                'payment_method' => $validated['payment_method'],
                'sender_number' => $validated['sender_number'] ?? ($validated['account_last4'] ?? null),
                'transaction_id' => $validated['transaction_id'],
                'bank_name' => $validated['bank_name'] ?? null,
                'account_holder' => $validated['account_holder'] ?? null,
                'account_last4' => $validated['account_last4'] ?? null,
                'proof_path' => $proofPath,
                'expires_at' => null,
            ]);

            if ($planId) {
                SubscriptionPayment::create([
                    'tenant_id' => $tenant->id,
                    'plan_id' => $planId,
                    'amount' => $mrr,
                    'method' => $validated['payment_method'],
                    'reference' => $validated['transaction_id'],
                    'bank_name' => $validated['bank_name'] ?? null,
                    'account_holder' => $validated['account_holder'] ?? null,
                    'account_last4' => $validated['account_last4'] ?? null,
                    'proof_path' => $proofPath,
                    'status' => 'pending',
                ]);
            }

            $store = Store::create([
                'tenant_id' => $tenant->id,
                'name' => $validated['business_name'].' Main Outlet',
                'code' => 'STORE-'.strtoupper(substr(uniqid(), -4)),
                'phone' => $validated['phone'] ?? null,
                'email' => $validated['email'],
                'currency_symbol' => '৳',
                'default_tax_rate' => 15.00,
                'is_active' => true,
            ]);

            $user = User::create([
                'tenant_id' => $tenant->id,
                'store_id' => $store->id,
                'name' => $validated['owner_name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'role' => 'merchant',
            ]);

            AuditLog::create([
                'tenant_id' => $tenant->id,
                'user_name' => $user->name,
                'action' => 'merchant_registered_pending_payment',
                'description' => "Merchant '{$tenant->name}' submitted registration with payment via {$validated['payment_method']} (Ref/TrxID: {$validated['transaction_id']}). Pending Super Admin verification.",
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

    public function updatePin(Request $request)
    {
        $validated = $request->validate([
            'pos_pin' => 'required|string|regex:/^[0-9]{4}$/',
            'current_password' => 'required|string',
        ]);

        /** @var User $user */
        $user = auth()->user();
        if (! Hash::check($validated['current_password'], $user->password)) {
            return back()->withErrors(['current_password' => 'Incorrect password entered.']);
        }

        $user->update([
            'pos_pin' => Hash::make($validated['pos_pin']),
        ]);

        return back()->with('success', 'Security PIN updated successfully.');
    }
}
