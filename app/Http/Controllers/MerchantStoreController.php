<?php

namespace App\Http\Controllers;

use App\Models\Store;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MerchantStoreController extends Controller
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

    public function index()
    {
        $tenantId = $this->getTenantId();
        $stores = Store::where('tenant_id', $tenantId)->withCount(['users', 'orders'])->get();

        return Inertia::render('Merchant/Stores', [
            'stores' => $stores,
        ]);
    }

    public function store(Request $request)
    {
        $tenantId = $this->getTenantId();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string',
            'email' => 'nullable|email',
            'address' => 'nullable|string',
            'currency_symbol' => 'nullable|string|max:10',
            'default_tax_rate' => 'nullable|numeric|min:0',
        ]);

        $tenant = Tenant::find($tenantId);
        $plan = $tenant ? \App\Models\SaaSPlan::where('name', 'LIKE', '%' . $tenant->plan_name . '%')->first() : null;
        $maxStores = $plan->max_stores ?? 5;
        $currentStores = Store::where('tenant_id', $tenantId)->count();

        if ($currentStores >= $maxStores) {
            return redirect()->back()->with('error', "Subscription plan limit of {$maxStores} stores reached for '{$tenant->plan_name}'. Please upgrade your SaaS tier to add more outlets.");
        }

        Store::create([
            'tenant_id' => $tenantId,
            'name' => $validated['name'],
            'code' => 'STORE-' . strtoupper(substr(uniqid(), -4)),
            'phone' => $validated['phone'] ?? null,
            'email' => $validated['email'] ?? null,
            'address' => $validated['address'] ?? null,
            'currency_symbol' => $validated['currency_symbol'] ?? '৳',
            'default_tax_rate' => $validated['default_tax_rate'] ?? 5.00,
            'is_active' => true,
        ]);

        return redirect()->back()->with('success', 'New Outlet Branch created successfully!');
    }

    public function update(Request $request, $id)
    {
        $tenantId = $this->getTenantId();
        $store = Store::where('id', $id)->where('tenant_id', $tenantId)->firstOrFail();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string',
            'email' => 'nullable|email',
            'address' => 'nullable|string',
            'currency_symbol' => 'nullable|string|max:10',
            'default_tax_rate' => 'nullable|numeric|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        $store->update($validated);

        return redirect()->back()->with('success', "Store outlet '{$store->name}' updated!");
    }

    public function delete($id)
    {
        $tenantId = $this->getTenantId();
        $store = Store::where('id', $id)->where('tenant_id', $tenantId)->firstOrFail();
        $storeName = $store->name;
        $store->delete();

        return redirect()->back()->with('success', "Store outlet '{$storeName}' deleted.");
    }
}
