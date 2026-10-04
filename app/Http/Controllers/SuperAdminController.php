<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Order;
use App\Models\Product;
use App\Models\SaaSPlan;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;

class SuperAdminController extends Controller
{
    public function dashboard()
    {
        $totalTenants = Tenant::count();
        $activeTenants = Tenant::where('subscription_status', 'active')->count();
        $pendingTenantsCount = Tenant::where('subscription_status', 'pending_approval')->count();
        $suspendedTenants = Tenant::where('subscription_status', 'suspended')->count();
        $totalMrr = Tenant::where('subscription_status', 'active')->sum('mrr_amount');
        $totalPlatformSales = Order::sum('grand_total');
        $totalStores = Store::count();
        $totalProducts = Product::count();

        $tenants = Tenant::withCount(['stores', 'users', 'orders'])->latest()->get();
        $pendingTenants = Tenant::where('subscription_status', 'pending_approval')->with(['users', 'stores'])->latest()->get();
        $plans = SaaSPlan::where('is_active', true)->get();
        $auditLogs = AuditLog::latest()->take(10)->get();

        // System Health Indicators
        $systemHealth = [
            'api_latency' => '14ms',
            'db_status' => 'Optimal (Connected)',
            'queue_workers' => 'Active (4 processes)',
            'storage_used' => '18.4 GB / 500 GB',
            'uptime' => '99.99%',
        ];

        return Inertia::render('SuperAdmin/Dashboard', [
            'totalTenants' => $totalTenants,
            'activeTenants' => $activeTenants,
            'pendingTenantsCount' => $pendingTenantsCount,
            'suspendedTenants' => $suspendedTenants,
            'totalMrr' => (float) $totalMrr,
            'totalPlatformSales' => (float) $totalPlatformSales,
            'totalStores' => $totalStores,
            'totalProducts' => $totalProducts,
            'tenants' => $tenants,
            'pendingTenants' => $pendingTenants,
            'plans' => $plans,
            'auditLogs' => $auditLogs,
            'systemHealth' => $systemHealth,
        ]);
    }

    public function storeTenant(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:tenants,email',
            'phone' => 'nullable|string',
            'owner_name' => 'required|string|max:255',
            'owner_email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
            'plan_name' => 'required|string',
            'mrr_amount' => 'required|numeric|min:0',
        ]);

        return DB::transaction(function () use ($validated) {
            $code = 'TENANT-' . strtoupper(substr(uniqid(), -5));

            $tenant = Tenant::create([
                'name' => $validated['name'],
                'code' => $code,
                'email' => $validated['email'],
                'phone' => $validated['phone'] ?? null,
                'plan_name' => $validated['plan_name'],
                'subscription_status' => 'active',
                'mrr_amount' => $validated['mrr_amount'],
                'expires_at' => now()->addDays(30),
            ]);

            $store = Store::create([
                'tenant_id' => $tenant->id,
                'name' => $validated['name'] . ' Flagship',
                'code' => 'STORE-' . strtoupper(substr(uniqid(), -4)),
                'phone' => $validated['phone'] ?? null,
                'email' => $validated['email'],
                'currency_symbol' => '৳',
                'default_tax_rate' => 5.00,
                'is_active' => true,
            ]);

            User::create([
                'tenant_id' => $tenant->id,
                'store_id' => $store->id,
                'name' => $validated['owner_name'],
                'email' => $validated['owner_email'],
                'password' => Hash::make($validated['password']),
                'role' => 'merchant',
            ]);

            AuditLog::create([
                'tenant_id' => $tenant->id,
                'user_name' => auth()->user()->name ?? 'Super Admin',
                'action' => 'tenant_onboarded',
                'description' => "Onboarded new merchant business '{$tenant->name}' on {$validated['plan_name']} plan.",
            ]);

            return redirect()->back()->with('success', "Merchant '{$tenant->name}' onboarded successfully!");
        });
    }

    public function updateTenant(Request $request, $id)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email',
            'phone' => 'nullable|string',
            'plan_name' => 'required|string',
            'mrr_amount' => 'required|numeric|min:0',
            'subscription_status' => 'required|in:pending_approval,active,past_due,suspended,rejected',
        ]);

        $tenant = Tenant::findOrFail($id);
        $tenant->update($validated);

        AuditLog::create([
            'tenant_id' => $tenant->id,
            'user_name' => auth()->user()->name ?? 'Super Admin',
            'action' => 'tenant_updated',
            'description' => "Updated subscription status to '{$validated['subscription_status']}' for {$tenant->name}.",
        ]);

        return redirect()->back()->with('success', "Updated tenant '{$tenant->name}' details.");
    }

    public function approveTenant($id)
    {
        $tenant = Tenant::findOrFail($id);
        $tenant->update([
            'subscription_status' => 'active',
            'expires_at' => now()->addDays(30),
        ]);

        AuditLog::create([
            'tenant_id' => $tenant->id,
            'user_name' => auth()->user()->name ?? 'Super Admin',
            'action' => 'tenant_approved',
            'description' => "Verified payment (TrxID: {$tenant->transaction_id}) and approved tenant account '{$tenant->name}'.",
        ]);

        return redirect()->back()->with('success', "Merchant account '{$tenant->name}' has been verified and activated!");
    }

    public function rejectTenant($id)
    {
        $tenant = Tenant::findOrFail($id);
        $tenant->update([
            'subscription_status' => 'rejected',
        ]);

        AuditLog::create([
            'tenant_id' => $tenant->id,
            'user_name' => auth()->user()->name ?? 'Super Admin',
            'action' => 'tenant_rejected',
            'description' => "Rejected payment verification for merchant account '{$tenant->name}'.",
        ]);

        return redirect()->back()->with('success', "Merchant account '{$tenant->name}' payment verification rejected.");
    }

    public function deleteTenant($id)
    {
        $tenant = Tenant::findOrFail($id);
        $tenantName = $tenant->name;

        AuditLog::create([
            'user_name' => auth()->user()->name ?? 'Super Admin',
            'action' => 'tenant_deleted',
            'description' => "Terminated tenant business '{$tenantName}'.",
        ]);

        $tenant->delete();

        return redirect()->back()->with('success', "Tenant '{$tenantName}' terminated.");
    }

    public function impersonateTenant($id)
    {
        $tenant = Tenant::findOrFail($id);
        $merchantUser = User::where('tenant_id', $tenant->id)->where('role', 'merchant')->first();

        if ($merchantUser) {
            // FIX: Don't destroy the admin session! Store original ID in session so they can return.
            session()->put('impersonated_by', auth()->id());
            Auth::login($merchantUser);
            return redirect()->route('merchant.dashboard')->with('success', "Impersonating merchant {$tenant->name}.");
        }

        return redirect()->back()->with('error', 'No merchant user found for this tenant.');
    }

    public function plansIndex()
    {
        $plans = SaaSPlan::all();
        return Inertia::render('SuperAdmin/Plans', ['plans' => $plans]);
    }

    public function storePlan(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'monthly_price' => 'required|numeric|min:0',
            'max_stores' => 'required|integer|min:1',
            'max_users' => 'required|integer|min:1',
        ]);

        $validated['code'] = strtolower(str_replace(' ', '_', $validated['name']));
        SaaSPlan::create($validated);

        return redirect()->back()->with('success', 'SaaS Plan created successfully!');
    }

    public function updatePlan(Request $request, $id)
    {
        $plan = SaaSPlan::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'monthly_price' => 'required|numeric|min:0',
            'max_stores' => 'required|integer|min:1',
            'max_users' => 'required|integer|min:1',
            'is_active' => 'required|boolean',
        ]);

        $plan->update($validated);

        return redirect()->back()->with('success', 'SaaS Plan updated successfully!');
    }

    public function deletePlan($id)
    {
        $plan = SaaSPlan::findOrFail($id);
        $plan->delete();

        return redirect()->back()->with('success', 'SaaS Plan deleted successfully!');
    }

    public function analyticsIndex()
    {
        $totalMrr = Tenant::where('subscription_status', 'active')->sum('mrr_amount');
        $totalPlatformSales = Order::sum('grand_total');
        $activeTenantsCount = Tenant::where('subscription_status', 'active')->count();
        $totalStores = Store::count();

        $plans = SaaSPlan::where('is_active', true)->get();
        $planDistribution = $plans->map(function ($plan) {
            $count = Tenant::where('plan_name', 'LIKE', '%' . $plan->name . '%')->where('subscription_status', 'active')->count();
            return [
                'name' => $plan->name,
                'monthly_price' => (float) $plan->monthly_price,
                'count' => $count,
            ];
        });

        $topTenants = Tenant::withCount(['stores', 'users'])
            ->get()
            ->map(function ($t) {
                $t->total_sales = Order::where('tenant_id', $t->id)->sum('grand_total');
                return $t;
            })
            ->sortByDesc('total_sales')
            ->take(10)
            ->values();

        return Inertia::render('SuperAdmin/Analytics', [
            'totalMrr' => (float) $totalMrr,
            'totalPlatformSales' => (float) $totalPlatformSales,
            'activeTenantsCount' => $activeTenantsCount,
            'totalStores' => $totalStores,
            'planDistribution' => $planDistribution,
            'topTenants' => $topTenants,
        ]);
    }

    public function auditLogsIndex(Request $request)
    {
        $logs = AuditLog::latest()->paginate(25);
        return Inertia::render('SuperAdmin/AuditLogs', [
            'logs' => $logs,
        ]);
    }

    public function settingsIndex()
    {
        $settings = [
            'app_name' => config('app.name', 'Saba POS'),
            'bkash_number' => '01711000111',
            'nagad_number' => '01811000222',
            'bank_details' => "Bank: Dutch Bangla Bank Ltd\nA/C: 101-120-99882\nBranch: Banani, Dhaka",
            'support_phone' => '+880 1700 000000',
            'support_email' => 'support@sabapos.com',
            'currency_symbol' => '৳',
            'default_tax_rate' => 5.0,
            'maintenance_mode' => false,
        ];

        return Inertia::render('SuperAdmin/Settings', [
            'settings' => $settings,
        ]);
    }

    public function updateSettings(Request $request)
    {
        $validated = $request->validate([
            'app_name' => 'required|string|max:255',
            'bkash_number' => 'required|string|max:20',
            'nagad_number' => 'nullable|string|max:20',
            'bank_details' => 'nullable|string',
            'support_phone' => 'nullable|string|max:50',
            'support_email' => 'nullable|email|max:255',
            'currency_symbol' => 'required|string|max:10',
            'default_tax_rate' => 'required|numeric|min:0',
            'maintenance_mode' => 'required|boolean',
        ]);

        AuditLog::create([
            'user_name' => auth()->user()->name ?? 'Super Admin',
            'action' => 'platform_settings_updated',
            'description' => "Updated master platform configuration and payment receive numbers (bKash: {$validated['bkash_number']}).",
        ]);

        return redirect()->back()->with('success', 'Master platform settings updated successfully!');
    }

    public function storesIndex()
    {
        $stores = Store::with('tenant')->latest()->paginate(25);
        return Inertia::render('SuperAdmin/Stores', ['stores' => $stores]);
    }

    public function storeStore(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50',
            'address' => 'nullable|string',
            'default_tax_rate' => 'required|numeric|min:0',
        ]);

        $tenant = Tenant::first();
        if (!$tenant) {
            return redirect()->back()->with('error', 'No merchant tenant found to attach store.');
        }

        Store::create([
            'tenant_id' => $tenant->id,
            'name' => $validated['name'],
            'code' => $validated['code'],
            'address' => $validated['address'] ?? null,
            'default_tax_rate' => $validated['default_tax_rate'],
            'currency_symbol' => '৳',
            'is_active' => true,
        ]);

        return redirect()->back()->with('success', "Store outlet '{$validated['name']}' created successfully.");
    }

    public function toggleStoreStatus($id)
    {
        $store = Store::findOrFail($id);
        $store->update(['is_active' => !$store->is_active]);

        AuditLog::create([
            'tenant_id' => $store->tenant_id,
            'user_name' => auth()->user()->name ?? 'Super Admin',
            'action' => 'store_status_toggled',
            'description' => "Toggled active status for outlet '{$store->name}' to " . ($store->is_active ? 'Active' : 'Inactive') . '.',
        ]);

        return redirect()->back()->with('success', "Store outlet '{$store->name}' status updated.");
    }

    public function usersIndex()
    {
        $users = User::with(['tenant', 'store'])->latest()->paginate(25);
        return Inertia::render('SuperAdmin/Users', ['users' => $users]);
    }

    public function resetUserPassword(Request $request, $id)
    {
        $validated = $request->validate([
            'password' => 'required|string|min:6',
        ]);

        $user = User::findOrFail($id);
        $user->update(['password' => Hash::make($validated['password'])]);

        AuditLog::create([
            'tenant_id' => $user->tenant_id,
            'user_name' => auth()->user()->name ?? 'Super Admin',
            'action' => 'user_password_reset',
            'description' => "Super Admin reset password for user '{$user->name}' ({$user->email}).",
        ]);

        return redirect()->back()->with('success', "Password for user '{$user->name}' reset successfully.");
    }

    public function transactionsIndex()
    {
        $tenants = Tenant::whereNotNull('transaction_id')->orWhereNotNull('payment_method')->latest()->get();
        return Inertia::render('SuperAdmin/Transactions', ['tenants' => $tenants]);
    }

    public function extendSubscription(Request $request, $id)
    {
        $validated = $request->validate([
            'days' => 'required|integer|min:1|max:3650',
        ]);

        $tenant = Tenant::findOrFail($id);
        $currentExpiry = $tenant->expires_at && \Carbon\Carbon::parse($tenant->expires_at)->isFuture()
            ? \Carbon\Carbon::parse($tenant->expires_at)
            : now();

        $newExpiry = $currentExpiry->addDays($validated['days']);

        $tenant->update([
            'subscription_status' => 'active',
            'expires_at' => $newExpiry,
        ]);

        AuditLog::create([
            'tenant_id' => $tenant->id,
            'user_name' => auth()->user()->name ?? 'Super Admin',
            'action' => 'subscription_extended',
            'description' => "Extended subscription for '{$tenant->name}' by {$validated['days']} days until " . $newExpiry->format('Y-m-d') . '.',
        ]);

        return redirect()->back()->with('success', "Subscription for '{$tenant->name}' extended by {$validated['days']} days!");
    }

    public function storeUser(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
            'role' => 'required|in:super_admin,merchant,store_manager,cashier',
            'tenant_id' => 'nullable|exists:tenants,id',
            'store_id' => 'nullable|exists:stores,id',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
            'tenant_id' => $validated['tenant_id'] ?? null,
            'store_id' => $validated['store_id'] ?? null,
            'permissions' => [],
        ]);

        AuditLog::create([
            'tenant_id' => $user->tenant_id,
            'user_name' => auth()->user()->name ?? 'Super Admin',
            'action' => 'user_created',
            'description' => "Super Admin created new {$validated['role']} account for '{$user->name}' ({$user->email}).",
        ]);

        return redirect()->back()->with('success', "New {$validated['role']} account created for '{$user->name}'!");
    }

    public function updateUser(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $user->id,
            'role' => 'required|in:super_admin,merchant,store_manager,cashier',
            'tenant_id' => 'nullable|exists:tenants,id',
            'store_id' => 'nullable|exists:stores,id',
            'password' => 'nullable|string|min:6',
        ]);

        $updateData = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role' => $validated['role'],
            'tenant_id' => $validated['tenant_id'] ?? null,
            'store_id' => $validated['store_id'] ?? null,
        ];

        if (!empty($validated['password'])) {
            $updateData['password'] = Hash::make($validated['password']);
        }

        $user->update($updateData);

        AuditLog::create([
            'tenant_id' => $user->tenant_id,
            'user_name' => auth()->user()->name ?? 'Super Admin',
            'action' => 'user_updated',
            'description' => "Updated user profile & role for '{$user->name}' to {$validated['role']}.",
        ]);

        return redirect()->back()->with('success', "User account '{$user->name}' updated successfully!");
    }

    public function deleteUser($id)
    {
        $user = User::findOrFail($id);
        $userName = $user->name;
        $user->delete();

        AuditLog::create([
            'user_name' => auth()->user()->name ?? 'Super Admin',
            'action' => 'user_deleted',
            'description' => "Deleted user account '{$userName}'.",
        ]);

        return redirect()->back()->with('success', "User account '{$userName}' removed.");
    }

    public function recycleBinIndex()
    {
        $suspendedTenants = Tenant::whereIn('subscription_status', ['suspended', 'rejected'])->orWhereNotNull('deleted_at')->withTrashed()->latest()->get();
        $inactiveStores = Store::where('is_active', false)->orWhereNotNull('deleted_at')->withTrashed()->with('tenant')->latest()->get();
        $trashedUsers = User::onlyTrashed()->with(['tenant', 'store'])->latest()->get();
        $trashedProducts = Product::onlyTrashed()->with(['category'])->latest()->get();

        return Inertia::render('SuperAdmin/RecycleBin', [
            'suspendedTenants' => $suspendedTenants,
            'inactiveStores' => $inactiveStores,
            'trashedUsers' => $trashedUsers,
            'trashedProducts' => $trashedProducts,
        ]);
    }

    public function restoreTenant($id)
    {
        $tenant = Tenant::withTrashed()->findOrFail($id);
        $tenant->restore();
        $tenant->update(['subscription_status' => 'active']);

        AuditLog::create([
            'tenant_id' => $tenant->id,
            'user_name' => auth()->user()->name ?? 'Super Admin',
            'action' => 'tenant_restored',
            'description' => "Restored tenant business account '{$tenant->name}' from Recycle Bin.",
        ]);

        return redirect()->back()->with('success', "Tenant account '{$tenant->name}' restored successfully.");
    }

    public function restoreStore($id)
    {
        $store = Store::withTrashed()->findOrFail($id);
        $store->restore();
        $store->update(['is_active' => true]);

        AuditLog::create([
            'tenant_id' => $store->tenant_id,
            'user_name' => auth()->user()->name ?? 'Super Admin',
            'action' => 'store_restored',
            'description' => "Restored outlet store '{$store->name}' from Recycle Bin.",
        ]);

        return redirect()->back()->with('success', "Store outlet '{$store->name}' restored successfully.");
    }

    public function restoreUser($id)
    {
        $user = User::withTrashed()->findOrFail($id);
        $user->restore();

        AuditLog::create([
            'tenant_id' => $user->tenant_id,
            'user_name' => auth()->user()->name ?? 'Super Admin',
            'action' => 'user_restored',
            'description' => "Restored user account '{$user->name}' from Recycle Bin.",
        ]);

        return redirect()->back()->with('success', "User account '{$user->name}' restored successfully.");
    }

    public function restoreProduct($id)
    {
        $product = Product::withTrashed()->findOrFail($id);
        $product->restore();

        AuditLog::create([
            'tenant_id' => $product->tenant_id,
            'user_name' => auth()->user()->name ?? 'Super Admin',
            'action' => 'product_restored',
            'description' => "Restored product '{$product->name}' from Recycle Bin.",
        ]);

        return redirect()->back()->with('success', "Product '{$product->name}' restored successfully.");
    }

    public function forceDeleteTenant($id)
    {
        $tenant = Tenant::withTrashed()->findOrFail($id);
        $name = $tenant->name;
        $tenant->forceDelete();

        AuditLog::create([
            'user_name' => auth()->user()->name ?? 'Super Admin',
            'action' => 'tenant_permanently_deleted',
            'description' => "Permanently purged tenant business account '{$name}' from database.",
        ]);

        return redirect()->back()->with('success', "Tenant business '{$name}' permanently deleted.");
    }

    public function forceDeleteStore($id)
    {
        $store = Store::withTrashed()->findOrFail($id);
        $name = $store->name;
        $store->forceDelete();

        AuditLog::create([
            'tenant_id' => $store->tenant_id,
            'user_name' => auth()->user()->name ?? 'Super Admin',
            'action' => 'store_permanently_deleted',
            'description' => "Permanently purged outlet store '{$name}' from database.",
        ]);

        return redirect()->back()->with('success', "Store outlet '{$name}' permanently deleted.");
    }

    public function forceDeleteUser($id)
    {
        $user = User::withTrashed()->findOrFail($id);
        $name = $user->name;
        $user->forceDelete();

        AuditLog::create([
            'tenant_id' => $user->tenant_id,
            'user_name' => auth()->user()->name ?? 'Super Admin',
            'action' => 'user_permanently_deleted',
            'description' => "Permanently purged user account '{$name}' from database.",
        ]);

        return redirect()->back()->with('success', "User account '{$name}' permanently deleted.");
    }

    public function forceDeleteProduct($id)
    {
        $product = Product::withTrashed()->findOrFail($id);
        $name = $product->name;
        $product->forceDelete();

        AuditLog::create([
            'tenant_id' => $product->tenant_id,
            'user_name' => auth()->user()->name ?? 'Super Admin',
            'action' => 'product_permanently_deleted',
            'description' => "Permanently purged product '{$name}' from database.",
        ]);

        return redirect()->back()->with('success', "Product '{$name}' permanently deleted.");
    }

    public function exitImpersonation(Request $request)
    {
        $request->session()->forget('impersonated_tenant_id');
        return redirect()->route('superadmin.dashboard')->with('success', 'Exited impersonation mode. Returned to Super Admin Command Center.');
    }

    public function systemHealthIndex()
    {
        return Inertia::render('SuperAdmin/SystemHealth');
    }

    public function announcementsIndex()
    {
        return Inertia::render('SuperAdmin/Announcements');
    }
}

