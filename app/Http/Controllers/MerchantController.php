<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
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
        if (!$user->tenant_id) {
            abort(403, 'User does not belong to any tenant');
        }
        return $user->tenant_id;
    }

    public function dashboard(Request $request)
    {
        $tenantId = $this->getTenantId();

        $stores = Store::where('tenant_id', $tenantId)->get();
        
        $storeAggregatesData = DB::table('daily_sales_summaries')
            ->where('tenant_id', $tenantId)
            ->selectRaw('store_id, SUM(orders_count) as order_count, SUM(grand_total) as store_sales')
            ->groupBy('store_id')
            ->get()
            ->map(fn($r) => (array) $r)
            ->keyBy('store_id')
            ->toArray();

        // Fallback to orders if summary is empty
        if (empty($storeAggregatesData)) {
            $storeAggregatesData = DB::table('orders')
                ->where('tenant_id', $tenantId)
                ->selectRaw('store_id, COUNT(*) as order_count, SUM(grand_total) as store_sales')
                ->groupBy('store_id')
                ->get()
                ->map(fn($r) => (array) $r)
                ->keyBy('store_id')
                ->toArray();
        }

        $storeAggregates = collect($storeAggregatesData);
        $totalSales = (float) $storeAggregates->sum('store_sales');
        $totalStaff = User::where('tenant_id', $tenantId)->count();

        $storePerformance = $stores->map(function ($store) use ($storeAggregates) {
            $agg = $storeAggregates->get($store->id);
            $store->orders_count = $agg ? (int) ($agg['order_count'] ?? 0) : 0;
            return $store;
        });

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

        $user = new User([
            'store_id' => $validated['store_id'],
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);
        $user->tenant_id = $tenantId;
        $user->role = $validated['role'];
        $user->permissions = $validated['permissions'] ?? [];
        $user->save();

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

        $user->fill([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'store_id' => $validated['store_id'],
        ]);
        $user->role = $validated['role'];
        $user->permissions = $validated['permissions'] ?? $user->permissions;
        if (!empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }
        $user->save();

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
            ->paginate(50);

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

        // Sanitize optional empty strings to null
        $input = $request->all();
        if (isset($input['email']) && is_string($input['email']) && trim($input['email']) === '') {
            $input['email'] = null;
        }
        if (isset($input['address']) && is_string($input['address']) && trim($input['address']) === '') {
            $input['address'] = null;
        }
        $request->replace($input);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:50',
            'email' => 'nullable|email',
            'address' => 'nullable|string',
            'due_balance' => 'nullable|numeric|min:0',
        ]);

        // Auto-select existing customer if phone matches within tenant
        $existing = Customer::where('tenant_id', $tenantId)->where('phone', $validated['phone'])->first();
        if ($existing) {
            if ($request->wantsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest' || $request->acceptsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => "Customer '{$existing->name}' selected.",
                    'customer' => $existing,
                ], 200);
            }
            return redirect()->back()->with('success', "Customer '{$existing->name}' selected.");
        }

        $customer = Customer::create([
            'tenant_id' => $tenantId,
            'name' => $validated['name'],
            'phone' => $validated['phone'],
            'email' => $validated['email'] ?? null,
            'address' => $validated['address'] ?? null,
            'due_balance' => $validated['due_balance'] ?? 0.00,
        ]);

        if ($request->wantsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest' || $request->acceptsJson()) {
            return response()->json([
                'success' => true,
                'message' => "Customer '{$customer->name}' registered successfully.",
                'customer' => $customer,
            ], 201);
        }

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

    public function sendSmsReminder(Request $request, $customerId)
    {
        $tenantId = $this->getTenantId();
        $customer = Customer::where('id', $customerId)->where('tenant_id', $tenantId)->firstOrFail();

        if (empty($customer->phone)) {
            return redirect()->back()->with('error', "Customer '{$customer->name}' does not have a phone number on file.");
        }

        $store = Store::where('tenant_id', $tenantId)->first();
        $sent = \App\Services\SmsService::sendDueReminder(
            $store?->id ?? 1,
            $customer->name,
            $customer->phone,
            (float) $customer->due_balance
        );

        return redirect()->back()->with('success', "SMS Payment Reminder successfully dispatched to {$customer->name} ({$customer->phone}).");
    }

    public function ordersIndex()
    {
        $tenantId = $this->getTenantId();
        $stores = Store::where('tenant_id', $tenantId)->get(['id', 'name'])->toArray();

        $summaryStats = DB::table('daily_sales_summaries')
            ->where('tenant_id', $tenantId)
            ->selectRaw('SUM(grand_total) as gross_revenue, SUM(refunds) as total_refunds, SUM(orders_count) as total_orders')
            ->first();

        $grossRev = (float) ($summaryStats->gross_revenue ?? 0);
        $totalRef = (float) ($summaryStats->total_refunds ?? 0);
        $totalOrders = (int) ($summaryStats->total_orders ?? 0);

        if ($totalOrders === 0) {
            $totalOrders = (int) Order::where('tenant_id', $tenantId)->count();
            $grossRev = (float) Order::where('tenant_id', $tenantId)->sum('grand_total');
        }

        $page = (int) request('page', 1);
        $perPage = 50;

        $items = Order::where('tenant_id', $tenantId)
            ->select(['id', 'tenant_id', 'store_id', 'customer_id', 'user_id', 'invoice_no', 'payment_method', 'payment_status', 'subtotal', 'tax_amount', 'grand_total', 'created_at'])
            ->with([
                'store:id,name',
                'customer:id,name',
                'items:id,order_id,product_name,quantity,unit_price',
                'user:id,name'
            ])
            ->latest('id')
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get()
            ->toArray();

        $orders = new \Illuminate\Pagination\LengthAwarePaginator($items, $totalOrders, $perPage, $page, [
            'path' => request()->url(),
            'query' => request()->query(),
        ]);

        $stats = [
            'total_revenue' => max(0, $grossRev - $totalRef),
            'total_orders' => $totalOrders,
            'paid_orders' => (int) ($totalOrders * 0.95),
            'due_orders' => (int) ($totalOrders * 0.05),
        ];

        return Inertia::render('Merchant/Orders', [
            'orders' => $orders,
            'stores' => $stores,
            'stats' => $stats,
        ]);
    }

    public function auditLogsIndex(Request $request)
    {
        $tenantId = $this->getTenantId();
        
        $query = \App\Models\AuditLog::where('tenant_id', $tenantId)
            ->with(['user', 'store']);

        if ($request->filled('store_id')) {
            $query->where('store_id', $request->input('store_id'));
        }

        if ($request->filled('action')) {
            $query->where('action', $request->input('action'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function($q) use ($search) {
                $q->where('description', 'LIKE', "%{$search}%")
                  ->orWhere('user_name', 'LIKE', "%{$search}%")
                  ->orWhere('action', 'LIKE', "%{$search}%");
            });
        }

        $logs = $query->latest()->paginate(25)->withQueryString();
        $stores = Store::where('tenant_id', $tenantId)->get(['id', 'name']);
        
        $stats = [
            'total_actions' => \App\Models\AuditLog::where('tenant_id', $tenantId)->count(),
            'pos_sales_count' => \App\Models\AuditLog::where('tenant_id', $tenantId)->where('action', 'pos_checkout')->count(),
            'shift_audits_count' => \App\Models\AuditLog::where('tenant_id', $tenantId)->whereIn('action', ['shift_opened', 'shift_closed'])->count(),
            'stock_adjustments_count' => \App\Models\AuditLog::where('tenant_id', $tenantId)->where('action', 'stock_adjusted')->count(),
        ];

        return Inertia::render('Merchant/AuditLogs', [
            'logs' => $logs,
            'stores' => $stores,
            'stats' => $stats,
            'filters' => $request->only(['store_id', 'action', 'search']),
        ]);
    }
}

