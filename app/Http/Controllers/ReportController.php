<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Purchase;
use App\Models\Store;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ReportController extends Controller
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

    public function profitLoss(Request $request)
    {
        $tenantId = $this->getTenantId();
        $startDate = $request->input('start_date', date('Y-m-01'));
        $endDate = $request->input('end_date', date('Y-m-d'));

        // 1. Total Gross Revenue (Tenant Scoped)
        $totalSales = Order::where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->sum('grand_total');

        // 2. Cost of Goods Sold (COGS) (Tenant Scoped)
        $orderItems = OrderItem::whereHas('order', function ($q) use ($startDate, $endDate, $tenantId) {
            $q->where('tenant_id', $tenantId)
              ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
        })->with('product')->get();

        $cogs = $orderItems->sum(function ($item) {
            $cost = $item->product ? (float) $item->product->purchase_cost : 0;
            return $cost * (float) $item->quantity;
        });

        // 3. Gross Profit = Sales - COGS
        $grossProfit = $totalSales - $cogs;

        // 4. Operating Expenses (Strict Tenant Scoped)
        $totalExpenses = Expense::where('tenant_id', $tenantId)
            ->whereBetween('date', [$startDate, $endDate])
            ->sum('amount');

        // 5. Net Profit = Gross Profit - Expenses
        $netProfit = $grossProfit - $totalExpenses;

        // 6. Sales Breakdown by Category
        $salesByCategory = OrderItem::whereHas('order', function ($q) use ($startDate, $endDate, $tenantId) {
            $q->where('tenant_id', $tenantId)
              ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
        })->with('product.category')->get()
          ->groupBy(fn($item) => $item->product->category->name ?? 'Uncategorized')
          ->map(fn($items, $cat) => [
              'category' => $cat,
              'qty' => (float) $items->sum('quantity'),
              'total' => (float) $items->sum('total'),
          ])->values();

        // 7. Sales Breakdown by Payment Method
        $salesByPayment = \App\Models\OrderPayment::whereHas('order', function ($q) use ($startDate, $endDate, $tenantId) {
            $q->where('tenant_id', $tenantId)
              ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
        })->get()
          ->groupBy('payment_method')
          ->map(fn($payments, $method) => [
              'method' => ucfirst(str_replace('_', ' ', $method)),
              'total' => (float) $payments->sum('amount'),
          ])->values();

        // 8. Sales Breakdown by Cashier Staff
        $salesByCashier = Order::where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->with('user')
            ->get()
            ->groupBy(fn($o) => $o->user->name ?? 'System Staff')
            ->map(fn($orders, $name) => [
                'cashier' => $name,
                'order_count' => $orders->count(),
                'total_sales' => (float) $orders->sum('grand_total'),
            ])->values();

        return Inertia::render('Reports/ProfitLoss', [
            'startDate' => $startDate,
            'endDate' => $endDate,
            'totalSales' => (float) $totalSales,
            'cogs' => (float) $cogs,
            'grossProfit' => (float) $grossProfit,
            'totalExpenses' => (float) $totalExpenses,
            'netProfit' => (float) $netProfit,
            'salesByCategory' => $salesByCategory,
            'salesByPayment' => $salesByPayment,
            'salesByCashier' => $salesByCashier,
        ]);
    }

    public function vatReport(Request $request)
    {
        $tenantId = $this->getTenantId();
        $startDate = $request->input('start_date', date('Y-m-01'));
        $endDate = $request->input('end_date', date('Y-m-d'));

        $orders = Order::where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->with(['store', 'customer'])
            ->latest()
            ->get();

        $totalSales = $orders->sum('grand_total');
        $totalVat = $orders->sum('tax_amount');
        $totalSubtotal = $orders->sum('subtotal');

        $vatByStore = $orders->groupBy(fn($o) => $o->store?->name ?? 'Default Outlet')
            ->map(fn($storeOrders, $storeName) => [
                'store_name' => $storeName,
                'bin_number' => $storeOrders->first()?->store?->bin_number ?? '123456789-0000',
                'order_count' => $storeOrders->count(),
                'net_amount' => (float) $storeOrders->sum('subtotal'),
                'vat_collected' => (float) $storeOrders->sum('tax_amount'),
                'gross_total' => (float) $storeOrders->sum('grand_total'),
            ])->values();

        return Inertia::render('Reports/Vat', [
            'startDate' => $startDate,
            'endDate' => $endDate,
            'totalSales' => (float) $totalSales,
            'totalSubtotal' => (float) $totalSubtotal,
            'totalVat' => (float) $totalVat,
            'vatByStore' => $vatByStore,
            'orders' => $orders,
        ]);
    }

    public function stockReport(Request $request)
    {
        $tenantId = $this->getTenantId();

        $products = \App\Models\Product::where('tenant_id', $tenantId)
            ->with(['category', 'stocks.store'])
            ->get();

        $batches = \App\Models\ProductBatch::where('tenant_id', $tenantId)
            ->with(['product', 'store'])
            ->where('quantity', '>', 0)
            ->orderBy('expiry_date', 'asc')
            ->get();

        $totalStockValue = $products->sum(function ($p) {
            $totalQty = $p->stocks->sum('quantity');
            return $totalQty * (float) $p->purchase_cost;
        });

        $totalPotentialRetailValue = $products->sum(function ($p) {
            $totalQty = $p->stocks->sum('quantity');
            return $totalQty * (float) $p->selling_price;
        });

        $expiringSoon = $batches->filter(function ($b) {
            return $b->expiry_date && $b->expiry_date->diffInDays(now(), false) >= -30;
        })->values();

        return Inertia::render('Reports/Stock', [
            'totalStockValue' => (float) $totalStockValue,
            'totalPotentialRetailValue' => (float) $totalPotentialRetailValue,
            'totalProducts' => $products->count(),
            'products' => $products,
            'batches' => $batches,
            'expiringSoon' => $expiringSoon,
        ]);
    }
}
