<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Expense;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Stock;
use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DashboardController extends Controller
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

    public function index(Request $request)
    {
        $tenantId = $this->getTenantId();
        $today = date('Y-m-d');
        
        $stores = Store::where('tenant_id', $tenantId)->where('is_active', true)->get();
        $storeId = (int) $request->input('store_id', $stores->first()->id ?? 0);

        $todaySales = Order::where('tenant_id', $tenantId)->whereDate('created_at', $today)->sum('grand_total');
        $todayOrdersCount = Order::where('tenant_id', $tenantId)->whereDate('created_at', $today)->count();
        $totalCustomers = Customer::where('tenant_id', $tenantId)->count();
        
        $lowStockProductsCount = Stock::whereHas('store', function ($q) use ($tenantId) {
            $q->where('tenant_id', $tenantId);
        })->where('quantity', '<=', 5)->count();

        // Recent Orders (Strict Tenant Scoped)
        $recentOrders = Order::where('tenant_id', $tenantId)
            ->with(['customer', 'store'])
            ->latest()
            ->take(6)
            ->get();

        // Top Selling Products (Strict Tenant Scoped)
        $topProducts = OrderItem::whereHas('order', function ($q) use ($tenantId) {
            $q->where('tenant_id', $tenantId);
        })->select('product_name', DB::raw('SUM(quantity) as total_qty'), DB::raw('SUM(total) as total_sales'))
            ->groupBy('product_name')
            ->orderByDesc('total_sales')
            ->take(5)
            ->get();

        return Inertia::render('Dashboard', [
            'todaySales' => (float) $todaySales,
            'todayOrdersCount' => $todayOrdersCount,
            'totalCustomers' => $totalCustomers,
            'lowStockCount' => $lowStockProductsCount,
            'recentOrders' => $recentOrders,
            'topProducts' => $topProducts,
            'stores' => $stores,
            'currentStoreId' => (int) $storeId,
        ]);
    }
}
