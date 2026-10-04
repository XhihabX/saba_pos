<?php

namespace App\Http\Controllers;

use App\Models\Customer;
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

    public function settings()
    {
        $tenantId = $this->getTenantId();
        $tenant = Tenant::findOrFail($tenantId);

        return Inertia::render('Merchant/Settings', [
            'tenant' => $tenant,
        ]);
    }

    public function updateSettings(Request $request)
    {
        $tenantId = $this->getTenantId();
        $tenant = Tenant::findOrFail($tenantId);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:50',
            'logo_url' => 'nullable|string|max:1000',
            'currency_symbol' => 'required|string|max:10',
            'default_tax_rate' => 'required|numeric|min:0|max:100',
            'receipt_header' => 'nullable|string',
            'receipt_footer' => 'nullable|string',
            'invoice_prefix' => 'required|string|max:20',
        ]);

        $tenant->update($validated);

        return redirect()->back()->with('success', 'Merchant store settings saved successfully!');
    }

    public function forceDeleteStaff($id)
    {
        $tenantId = $this->getTenantId();
        $user = User::withTrashed()->where('id', $id)->where('tenant_id', $tenantId)->firstOrFail();
        $name = $user->name;
        $user->forceDelete();

        return redirect()->back()->with('success', "Staff user '{$name}' permanently deleted.");
    }

    public function restoreStaff($id)
    {
        $tenantId = $this->getTenantId();
        $user = User::withTrashed()->where('id', $id)->where('tenant_id', $tenantId)->firstOrFail();
        $user->restore();

        return redirect()->back()->with('success', "Staff user '{$user->name}' restored.");
    }

    public function payCustomerDue(Request $request, $id)
    {
        $tenantId = $this->getTenantId();
        $customer = Customer::where('id', $id)->where('tenant_id', $tenantId)->firstOrFail();

        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|string',
            'notes' => 'nullable|string',
        ]);

        $payAmount = (float) $validated['amount'];
        $customer->decrement('due_balance', $payAmount);

        if ($customer->due_balance < 0) {
            $customer->update(['due_balance' => 0]);
        }

        return redirect()->back()->with('success', "Collected payment of ৳{$payAmount} for customer '{$customer->name}'. Updated due balance: ৳{$customer->due_balance}.");
    }

    public function customersIndex()
    {
        $tenantId = $this->getTenantId();
        $customers = Customer::where('tenant_id', $tenantId)
            ->withCount('orders')
            ->latest()
            ->get();

        $stats = [
            'total_customers' => Customer::where('tenant_id', $tenantId)->count(),
            'total_due' => (float) Customer::where('tenant_id', $tenantId)->sum('due_balance'),
            'total_points' => (int) Customer::where('tenant_id', $tenantId)->sum('points'),
        ];

        return Inertia::render('Merchant/Customers', [
            'customers' => $customers,
            'stats' => $stats,
        ]);
    }

    public function storeCustomer(Request $request)
    {
        $tenantId = $this->getTenantId();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:50',
            'email' => 'nullable|email',
            'address' => 'nullable|string',
            'due_balance' => 'nullable|numeric|min:0',
        ]);

        Customer::create([
            'tenant_id' => $tenantId,
            'name' => $validated['name'],
            'phone' => $validated['phone'],
            'email' => $validated['email'] ?? null,
            'address' => $validated['address'] ?? null,
            'due_balance' => $validated['due_balance'] ?? 0.00,
        ]);

        return redirect()->back()->with('success', "Customer '{$validated['name']}' registered.");
    }

    public function updateCustomer(Request $request, $id)
    {
        $tenantId = $this->getTenantId();
        $customer = Customer::where('id', $id)->where('tenant_id', $tenantId)->firstOrFail();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:50',
            'email' => 'nullable|email',
            'address' => 'nullable|string',
            'due_balance' => 'nullable|numeric|min:0',
        ]);

        $customer->update($validated);

        return redirect()->back()->with('success', "Customer '{$customer->name}' profile updated.");
    }

    public function deleteCustomer($id)
    {
        $tenantId = $this->getTenantId();
        $customer = Customer::where('id', $id)->where('tenant_id', $tenantId)->firstOrFail();
        $name = $customer->name;
        $customer->delete();

        return redirect()->back()->with('success', "Customer '{$name}' archived.");
    }

    public function ordersIndex()
    {
        $tenantId = $this->getTenantId();
        $orders = Order::where('tenant_id', $tenantId)
            ->with(['store', 'customer', 'items.product', 'user'])
            ->latest()
            ->get();
        $stores = Store::where('tenant_id', $tenantId)->get();

        $stats = [
            'total_revenue' => (float) Order::where('tenant_id', $tenantId)->sum('grand_total'),
            'total_orders' => Order::where('tenant_id', $tenantId)->count(),
            'paid_orders' => Order::where('tenant_id', $tenantId)->where('payment_status', 'paid')->count(),
            'due_orders' => Order::where('tenant_id', $tenantId)->whereIn('payment_status', ['due', 'partial'])->count(),
        ];

        return Inertia::render('Merchant/Orders', [
            'orders' => $orders,
            'stores' => $stores,
            'stats' => $stats,
        ]);
    }
}

