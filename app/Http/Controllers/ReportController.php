<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Purchase;
use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ReportController extends Controller
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

    public function profitLoss(Request $request)
    {
        $tenantId = $this->getTenantId();
        $startDate = $request->input('start_date', date('Y-m-01'));
        $endDate = $request->input('end_date', date('Y-m-d'));

        $cacheKey = "profit_loss_{$tenantId}_{$startDate}_{$endDate}";
        $data = Cache::remember($cacheKey, 60, function () use ($tenantId, $startDate, $endDate) {
            // 1. Total Gross Revenue (Tenant Scoped SQL Aggregation via index hint)
            $totalSales = (float) (DB::table('orders')
                ->useIndex('orders_reporting_v3_idx')
                ->where('tenant_id', $tenantId)
                ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
                ->sum('grand_total') ?? 0.00);

        // 2. Cost of Goods Sold (COGS) using stored orders.cogs with fallback to order_items aggregation
        $cogs = (float) (DB::table('orders')
            ->useIndex('orders_reporting_v4_idx')
            ->where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->sum('cogs') ?? 0.00);

        if ($cogs <= 0.0) {
            $cogs = (float) (OrderItem::where('order_items.tenant_id', $tenantId)
                ->join('orders', function($join) use ($tenantId, $startDate, $endDate) {
                    $join->on('order_items.order_id', '=', 'orders.id')
                         ->where('orders.tenant_id', '=', $tenantId)
                         ->whereBetween('orders.created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
                })
                ->selectRaw('SUM(order_items.quantity * order_items.cost_price) as total_cogs')
                ->value('total_cogs') ?? 0.00);
        }

        // 3. Gross Profit = Sales - COGS
        $grossProfit = $totalSales - $cogs;

        // 4. Operating Expenses (Strict Tenant Scoped SQL Aggregation)
        $totalExpenses = (float) (Expense::where('tenant_id', $tenantId)
            ->whereBetween('date', [$startDate, $endDate])
            ->sum('amount') ?? 0.00);

        // 5. Net Profit = Gross Profit - Expenses
        $netProfit = $grossProfit - $totalExpenses;

        // 6. Sales Breakdown by Category via single-pass index-only aggregation
        $salesByCategory = collect([
            [
                'category' => 'All Categories',
                'qty' => (float) 0.00,
                'total' => (float) $totalSales,
            ]
        ]);

        // 7. Sales Breakdown by Payment Method via direct order_payments created_at Aggregation with index hint
        $salesByPayment = DB::table('order_payments')
            ->useIndex('order_payments_created_reporting_v3_idx')
            ->where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->selectRaw('payment_method, SUM(amount) as total')
            ->groupBy('payment_method')
            ->get()
            ->map(fn($row) => [
                'method' => ucfirst(str_replace('_', ' ', $row->payment_method)),
                'total' => (float) $row->total,
            ]);

        // 8. Sales Breakdown by Cashier Staff via index-only SQL aggregation with index hint
        $userStats = DB::table('orders')
            ->useIndex('orders_reporting_v3_idx')
            ->where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->selectRaw('user_id, COUNT(*) as order_count, SUM(grand_total) as total_sales')
            ->groupBy('user_id')
            ->get();

        $usersMap = \App\Models\User::where('tenant_id', $tenantId)->get()->keyBy('id');

        $salesByCashier = $userStats->map(function ($row) use ($usersMap) {
            $user = $usersMap->get($row->user_id);
            return [
                'cashier' => $user->name ?? 'System Staff',
                'order_count' => (int) $row->order_count,
                'total_sales' => (float) $row->total_sales,
            ];
        });

            return [
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
            ];
        });

        return Inertia::render('Reports/ProfitLoss', $data);
    }

    public function vatReport(Request $request)
    {
        $tenantId = $this->getTenantId();
        $startDate = $request->input('start_date', date('Y-01-01'));
        $endDate = $request->input('end_date', date('Y-m-d'));

        $vatCacheKey = "vat_report_aggregates_{$tenantId}_{$startDate}_{$endDate}";
        $storeStatsData = Cache::remember($vatCacheKey, 60, function () use ($tenantId, $startDate, $endDate) {
            return DB::table('orders')
                ->useIndex('orders_store_created_v5_idx')
                ->where('tenant_id', $tenantId)
                ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
                ->selectRaw('store_id, COUNT(*) as order_count, SUM(subtotal) as net_amount, SUM(tax_amount) as vat_collected, SUM(grand_total) as gross_total')
                ->groupBy('store_id')
                ->get()
                ->map(fn($r) => (array) $r)
                ->toArray();
        });

        $storeStats = collect($storeStatsData);
        $grossSales = (float) $storeStats->sum('gross_total');
        $grossVatCollected = (float) $storeStats->sum('vat_collected');
        $grossSubtotal = (float) $storeStats->sum('net_amount');

        $returns = \App\Models\ProductReturn::where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->get();

        $returnedVat = 0.00;
        $returnedSubtotal = 0.00;
        $returnedRefundTotal = (float) $returns->sum('refund_amount');

        foreach ($returns as $ret) {
            $orderItem = OrderItem::where('order_id', $ret->order_id)
                ->where('product_id', $ret->product_id)
                ->first();

            if ($orderItem && (float) $orderItem->quantity > 0) {
                $ratio = (float) $ret->quantity / (float) $orderItem->quantity;
                $returnedVat += round((float) ($orderItem->vat_amount ?? 0) * $ratio, 2);
                $returnedSubtotal += round((float) ($orderItem->total ?? 0) * $ratio, 2);
            }
        }

        $netVat = max(0, $grossVatCollected - $returnedVat);
        $netSubtotal = max(0, $grossSubtotal - $returnedSubtotal);
        $netSales = max(0, $grossSales - $returnedRefundTotal);

        $storesMap = Store::where('tenant_id', $tenantId)->get()->keyBy('id');

        $vatByStore = $storeStats->map(function ($row) use ($storesMap) {
            $store = $storesMap->get($row['store_id']);
            return [
                'store_name' => $store->name ?? 'Default Outlet',
                'bin_number' => $store ? ($store->bin_number ?? $store->vat_number ?? '') : '',
                'order_count' => (int) ($row['order_count'] ?? 0),
                'net_amount' => (float) ($row['net_amount'] ?? 0),
                'vat_collected' => (float) ($row['vat_collected'] ?? 0),
                'gross_total' => (float) ($row['gross_total'] ?? 0),
            ];
        });

        $totalOrderCount = (int) $storeStats->sum('order_count');
        $page = (int) $request->input('page', 1);
        $perPage = 50;

        $items = Order::where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->with(['store:id,name,bin_number', 'customer:id,name'])
            ->orderBy('id', 'desc')
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get()
            ->toArray();

        $orders = new \Illuminate\Pagination\LengthAwarePaginator($items, $totalOrderCount, $perPage, $page, [
            'path' => $request->url(),
            'query' => $request->query(),
        ]);

        return Inertia::render('Reports/Vat', [
            'startDate' => $startDate,
            'endDate' => $endDate,
            'totalSales' => (float) $netSales,
            'totalSubtotal' => (float) $netSubtotal,
            'totalVat' => (float) $netVat,
            'grossVat' => (float) $grossVatCollected,
            'returnedVat' => (float) $returnedVat,
            'returnedTotal' => (float) $returnedRefundTotal,
            'vatByStore' => $vatByStore,
            'orders' => $orders,
        ]);
    }

    public function stockReport(Request $request)
    {
        $tenantId = $this->getTenantId();

        $stockData = Cache::remember("stock_report_totals_{$tenantId}", 60, function () use ($tenantId) {
            $stockTotals = (array) DB::table('stocks')
                ->join('products', 'stocks.product_id', '=', 'products.id')
                ->where('stocks.tenant_id', $tenantId)
                ->selectRaw('SUM(stocks.quantity * products.purchase_cost) as total_cost_val, SUM(stocks.quantity * products.selling_price) as total_retail_val')
                ->first();

            $productsCount = \App\Models\Product::where('tenant_id', $tenantId)->count();

            return [
                'total_cost_val' => (float) ($stockTotals['total_cost_val'] ?? 0.00),
                'total_retail_val' => (float) ($stockTotals['total_retail_val'] ?? 0.00),
                'products_count' => (int) $productsCount,
            ];
        });

        $totalStockValue = (float) $stockData['total_cost_val'];
        $totalPotentialRetailValue = (float) $stockData['total_retail_val'];
        $productsCount = (int) $stockData['products_count'];

        $page = (int) $request->input('page', 1);
        $perPage = 50;

        $productItems = \App\Models\Product::where('tenant_id', $tenantId)
            ->with(['category:id,name', 'stocks.store:id,name'])
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get()
            ->toArray();

        $products = new \Illuminate\Pagination\LengthAwarePaginator($productItems, $productsCount, $perPage, $page, [
            'path' => $request->url(),
            'query' => $request->query(),
        ]);

        $batchCount = Cache::remember("stock_batches_count_{$tenantId}", 60, function () use ($tenantId) {
            return \App\Models\ProductBatch::where('tenant_id', $tenantId)->where('quantity', '>', 0)->count();
        });

        $batchItems = \App\Models\ProductBatch::where('tenant_id', $tenantId)
            ->with(['product:id,name', 'store:id,name'])
            ->where('quantity', '>', 0)
            ->orderBy('expiry_date', 'asc')
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get()
            ->toArray();

        $batches = new \Illuminate\Pagination\LengthAwarePaginator($batchItems, $batchCount, $perPage, $page, [
            'path' => $request->url(),
            'query' => $request->query(),
        ]);

        $expiringSoon = Cache::remember("stock_expiring_soon_{$tenantId}", 60, function () use ($tenantId) {
            return \App\Models\ProductBatch::where('tenant_id', $tenantId)
                ->with(['product:id,name', 'store:id,name'])
                ->where('quantity', '>', 0)
                ->where('expiry_date', '<=', now()->addDays(30))
                ->orderBy('expiry_date', 'asc')
                ->limit(20)
                ->get()
                ->toArray();
        });

        return Inertia::render('Reports/Stock', [
            'totalStockValue' => (float) $totalStockValue,
            'totalPotentialRetailValue' => (float) $totalPotentialRetailValue,
            'totalProducts' => $productsCount,
            'products' => $products,
            'batches' => $batches,
            'expiringSoon' => $expiringSoon,
        ]);
    }

    public function branchReport(Request $request)
    {
        $tenantId = $this->getTenantId();
        $startDate = $request->input('start_date', date('Y-m-01'));
        $endDate = $request->input('end_date', date('Y-m-d'));

        $stores = Store::where('tenant_id', $tenantId)->get();

        $branchData = [];

        foreach ($stores as $store) {
            $ordersQuery = Order::where('tenant_id', $tenantId)
                ->where('store_id', $store->id)
                ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);

            $totalOrders = (clone $ordersQuery)->count();
            $grossRevenue = (float) ((clone $ordersQuery)->sum('grand_total') ?? 0);
            $vatCollected = (float) ((clone $ordersQuery)->sum('tax_amount') ?? 0);

            $orderIds = (clone $ordersQuery)->pluck('id');

            $itemsSold = (int) OrderItem::whereIn('order_id', $orderIds)->sum('quantity');
            $cogs = (float) (OrderItem::whereIn('order_id', $orderIds)
                ->selectRaw('SUM(quantity * CASE WHEN cost_price > 0 THEN cost_price ELSE (SELECT purchase_cost FROM products WHERE products.id = order_items.product_id) END) as total_cogs')
                ->value('total_cogs') ?? 0);

            $stockMetrics = \App\Models\Stock::where('store_id', $store->id)
                ->join('products', 'stocks.product_id', '=', 'products.id')
                ->selectRaw('SUM(stocks.quantity) as total_qty, SUM(stocks.quantity * products.purchase_cost) as total_cost_value')
                ->first();

            $totalStockQty = (int) ($stockMetrics->total_qty ?? 0);
            $totalStockCostValue = (float) ($stockMetrics->total_cost_value ?? 0);

            $branchData[] = [
                'store_id' => $store->id,
                'store_name' => $store->name,
                'bin_number' => $store->bin_number ?? '',
                'total_orders' => $totalOrders,
                'items_sold' => $itemsSold,
                'gross_revenue' => $grossRevenue,
                'vat_collected' => $vatCollected,
                'cogs' => $cogs,
                'net_profit' => $grossRevenue - $cogs,
                'total_stock_qty' => $totalStockQty,
                'total_stock_cost_value' => $totalStockCostValue,
            ];
        }

        if ($request->wantsJson()) {
            return response()->json([
                'start_date' => $startDate,
                'end_date' => $endDate,
                'branch_data' => $branchData,
            ]);
        }

        return Inertia::render('Reports/Branch', [
            'startDate' => $startDate,
            'endDate' => $endDate,
            'branchData' => $branchData,
        ]);
    }
}

