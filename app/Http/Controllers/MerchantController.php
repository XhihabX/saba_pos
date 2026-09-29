<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;

class MerchantController extends Controller
{
    private function getTenantId()
    {
        $user = auth()->user();
        if (!$user) {
            abort(401, 'Unauthenticated');
        }
        if ($user->role === 'super_admin') {
            return Store::first()->tenant_id ?? 1;
        }
        if (!$user->tenant_id) {
            abort(403, 'Merchant tenant context required');
        }
        return $user->tenant_id;
    }

    public function dashboard(Request $request)
    {
        $tenantId = $this->getTenantId();

        $stores = Store::where('tenant_id', $tenantId)->get();
        $totalSales = Order::where('tenant_id', $tenantId)->sum('grand_total');
        $totalStaff = User::where('tenant_id', $tenantId)->count();

        $storePerformance = Store::where('tenant_id', $tenantId)
            ->withCount(['orders'])
            ->get();

        return Inertia::render('Merchant/Dashboard', [
            'stores' => $stores,
            'totalSales' => (float) $totalSales,
            'totalStaff' => $totalStaff,
            'storePerformance' => $storePerformance,
        ]);
    }

    public function staffIndex(Request $request)
    {
        $tenantId = $this->getTenantId();

        $users = User::where('tenant_id', $tenantId)->with('store')->latest()->get();
        $stores = Store::where('tenant_id', $tenantId)->get();

        return Inertia::render('Merchant/Users', [
            'users' => $users,
            'stores' => $stores,
        ]);
    }

    public function storeStaff(Request $request)
    {
        $tenantId = $this->getTenantId();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
            'role' => 'required|in:store_manager,cashier',
            'store_id' => 'required|exists:stores,id',
            'permissions' => 'nullable|array',
        ]);

        // Ensure target store belongs to merchant tenant
        Store::where('id', $validated['store_id'])->where('tenant_id', $tenantId)->firstOrFail();

        // Enforce subscription plan max_users limit
        $tenant = Tenant::find($tenantId);
        $plan = $tenant ? \App\Models\SaaSPlan::where('name', 'LIKE', '%' . $tenant->plan_name . '%')->first() : null;
        $maxUsers = $plan->max_users ?? 10;
        $currentUsers = User::where('tenant_id', $tenantId)->count();

        if ($currentUsers >= $maxUsers) {
            return redirect()->back()->with('error', "Subscription plan limit of {$maxUsers} staff accounts reached for '{$tenant->plan_name}'. Please upgrade your SaaS tier to add more staff users.");
        }

        User::create([
            'tenant_id' => $tenantId,
            'store_id' => $validated['store_id'],
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
            'permissions' => $validated['permissions'] ?? [],
        ]);

        return redirect()->back()->with('success', 'Staff account created successfully!');
    }

    public function updateStaff(Request $request, $id)
    {
        $tenantId = $this->getTenantId();
        $user = User::where('id', $id)->where('tenant_id', $tenantId)->firstOrFail();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $user->id,
            'role' => 'required|in:store_manager,cashier',
            'store_id' => 'required|exists:stores,id',
            'password' => 'nullable|string|min:6',
            'permissions' => 'nullable|array',
        ]);

        Store::where('id', $validated['store_id'])->where('tenant_id', $tenantId)->firstOrFail();

        $updateData = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role' => $validated['role'],
            'store_id' => $validated['store_id'],
            'permissions' => $validated['permissions'] ?? $user->permissions,
        ];

        if (!empty($validated['password'])) {
            $updateData['password'] = Hash::make($validated['password']);
        }

        $user->update($updateData);

        return redirect()->back()->with('success', "Staff account '{$user->name}' updated!");
    }

    public function deleteStaff($id)
    {
        $tenantId = $this->getTenantId();
        $user = User::where('id', $id)->where('tenant_id', $tenantId)->firstOrFail();
        $userName = $user->name;
        $user->delete();

        return redirect()->back()->with('success', "Staff account '{$userName}' removed.");
    }

    public function subscription()
    {
        $tenantId = $this->getTenantId();
        $tenant = Tenant::findOrFail($tenantId);

        return Inertia::render('Merchant/Subscription', [
            'tenant' => $tenant,
        ]);
    }
}

