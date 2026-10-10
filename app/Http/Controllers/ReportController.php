<?php

namespace App\Http\Controllers;

use App\Models\DailySalesSummary;
use App\Models\DiscrepancyAlert;
use App\Models\Expense;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\Stock;
use App\Models\Store;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ReportController extends Controller
{
    private function getTenantId()
    {
        $user = auth()->user();
        if (! $user) {
            abort(401, 'Unauthenticated');
        }
        if (! $user->tenant_id) {
            abort(403, 'User does not belong to any tenant');
        }

        return $user->tenant_id;
    }

    public function profitLoss(Request $request)
    {
        $tenantId = $this->getTenantId();
        $startDate = $request->input('start_date', date('Y-01-01'));
        $endDate = $request->input('end_date', date('Y-m-d'));

        // Query DailySalesSummary table (Pre-aggregated daily store metrics)
        $summary = DB::table('daily_sales_summaries')
            ->where('tenant_id', $tenantId)
            ->whereBetween('date', [$startDate, $endDate])
            ->selectRaw('
                SUM(grand_total) as total_sales,
                SUM(cogs) as total_cogs,
                SUM(refunds) as total_refunds,
                SUM(cash_total) as cash_total,
                SUM(card_total) as card_total,
                SUM(bkash_total) as bkash_total,
                SUM(nagad_total) as nagad_total,
                SUM(rocket_total) as rocket_total,
                SUM(upay_total) as upay_total,
                SUM(due_total) as due_total,
                SUM(other_total) as other_total
            ')
            ->first();

        $grossSales = (float) ($summary->total_sales ?? 0.00);
        $totalRefunds = (float) ($summary->total_refunds ?? 0.00);
        $totalSales = max(0, $grossSales - $totalRefunds);
        $cogs = (float) ($summary->total_cogs ?? 0.00);

        // Fallback for COGS if summary table is empty / not backfilled
        if ($cogs <= 0.0 && $totalSales > 0) {
            $cogs = (float) (DB::table('orders')
                ->where('tenant_id', $tenantId)
                ->whereBetween('created_at', [$startDate.' 00:00:00', $endDate.' 23:59:59'])
                ->sum('cogs') ?? 0.00);
        }

        // Gross Profit = Net Sales - COGS
        $grossProfit = $totalSales - $cogs;

        // Operating Expenses (Strict Tenant Scoped SQL Aggregation)
        $totalExpenses = (float) (Expense::where('tenant_id', $tenantId)
            ->whereBetween('date', [$startDate, $endDate])
            ->sum('amount') ?? 0.00);

        // Net Profit = Gross Profit - Expenses
        $netProfit = $grossProfit - $totalExpenses;

        $salesByCategory = collect([
            [
                'category' => 'All Categories',
                'qty' => (float) 0.00,
                'total' => (float) $totalSales,
            ],
        ]);

        // Sales Breakdown by Payment Method via summary table
        $salesByPayment = collect([
            ['method' => 'Cash', 'total' => (float) ($summary->cash_total ?? 0.00)],
            ['method' => 'Card', 'total' => (float) ($summary->card_total ?? 0.00)],
            ['method' => 'bKash', 'total' => (float) ($summary->bkash_total ?? 0.00)],
            ['method' => 'Nagad', 'total' => (float) ($summary->nagad_total ?? 0.00)],
            ['method' => 'Rocket', 'total' => (float) ($summary->rocket_total ?? 0.00)],
            ['method' => 'Upay', 'total' => (float) ($summary->upay_total ?? 0.00)],
            ['method' => 'Due / Credit', 'total' => (float) ($summary->due_total ?? 0.00)],
            ['method' => 'Other', 'total' => (float) ($summary->other_total ?? 0.00)],
        ])->filter(fn ($item) => $item['total'] > 0)->values();

        // Sales Breakdown by Cashier Staff (Driven via direct index range query per user)
        $users = User::where('tenant_id', $tenantId)->get(['id', 'name']);
        $salesByCashier = collect();

        foreach ($users as $u) {
            $stat = DB::table('orders')
                ->useIndex('orders_cashier_reporting_v6_idx')
                ->where('tenant_id', $tenantId)
                ->where('user_id', $u->id)
                ->whereBetween('created_at', [$startDate.' 00:00:00', $endDate.' 23:59:59'])
                ->selectRaw('COUNT(*) as order_count, SUM(grand_total) as total_sales')
                ->first();

            $salesCount = (int) ($stat->order_count ?? 0);
            if ($salesCount > 0) {
                $salesByCashier->push([
                    'cashier' => $u->name ?? 'System Staff',
                    'order_count' => $salesCount,
                    'total_sales' => (float) ($stat->total_sales ?? 0.00),
                ]);
            }
        }

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
        $startDate = $request->input('start_date', date('Y-01-01'));
        $endDate = $request->input('end_date', date('Y-m-d'));

        // Query DailySalesSummary table (Real-time, zero report caching)
        $storeStatsData = DB::table('daily_sales_summaries')
            ->where('tenant_id', $tenantId)
            ->whereBetween('date', [$startDate, $endDate])
            ->selectRaw('store_id, SUM(orders_count) as order_count, SUM(subtotal) as net_amount, SUM(tax_amount) as vat_collected, SUM(grand_total) as gross_total, SUM(refunds) as refunds')
            ->groupBy('store_id')
            ->get()
            ->map(fn ($r) => (array) $r)
            ->toArray();

        // Fallback to orders table if summary table has not been populated
        if (empty($storeStatsData)) {
            $storeStatsData = DB::table('orders')
                ->where('tenant_id', $tenantId)
                ->whereBetween('created_at', [$startDate.' 00:00:00', $endDate.' 23:59:59'])
                ->selectRaw('store_id, COUNT(*) as order_count, SUM(subtotal) as net_amount, SUM(tax_amount) as vat_collected, SUM(grand_total) as gross_total, 0.00 as refunds')
                ->groupBy('store_id')
                ->get()
                ->map(fn ($r) => (array) $r)
                ->toArray();
        }

        $storeStats = collect($storeStatsData);
        $grossSales = (float) $storeStats->sum('gross_total');
        $grossVatCollected = (float) $storeStats->sum('vat_collected');
        $grossSubtotal = (float) $storeStats->sum('net_amount');
        $returnedRefundTotal = (float) $storeStats->sum('refunds');
        if ($returnedRefundTotal <= 0) {
            $returnedRefundTotal = (float) DB::table('product_returns')
                ->where('tenant_id', $tenantId)
                ->whereBetween('created_at', [$startDate.' 00:00:00', $endDate.' 23:59:59'])
                ->sum('refund_amount');
        }

        // Estimate proportional VAT returned (15% average VAT component of refunds)
        $returnedVat = round($returnedRefundTotal * 0.130435, 2); // 15/115 for 15% inclusive
        $returnedSubtotal = max(0, $returnedRefundTotal - $returnedVat);

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
            ->whereBetween('created_at', [$startDate.' 00:00:00', $endDate.' 23:59:59'])
            ->with(['store:id,name,bin_number', 'customer:id,name'])
            ->orderBy('id', 'desc')
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get()
            ->toArray();

        $orders = new LengthAwarePaginator($items, $totalOrderCount, $perPage, $page, [
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

            $productsCount = Product::where('tenant_id', $tenantId)->count();

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

        $productItems = Product::where('tenant_id', $tenantId)
            ->with(['category:id,name', 'stocks.store:id,name'])
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get()
            ->toArray();

        $products = new LengthAwarePaginator($productItems, $productsCount, $perPage, $page, [
            'path' => $request->url(),
            'query' => $request->query(),
        ]);

        $batchCount = Cache::remember("stock_batches_count_{$tenantId}", 60, function () use ($tenantId) {
            return ProductBatch::where('tenant_id', $tenantId)->where('quantity', '>', 0)->count();
        });

        $batchItems = ProductBatch::where('tenant_id', $tenantId)
            ->with(['product:id,name', 'store:id,name'])
            ->where('quantity', '>', 0)
            ->orderBy('expiry_date', 'asc')
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get()
            ->toArray();

        $batches = new LengthAwarePaginator($batchItems, $batchCount, $perPage, $page, [
            'path' => $request->url(),
            'query' => $request->query(),
        ]);

        $expiringSoon = Cache::remember("stock_expiring_soon_{$tenantId}", 60, function () use ($tenantId) {
            return ProductBatch::where('tenant_id', $tenantId)
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
        $user = auth()->user();
        if (! $user || ! in_array($user->role, ['super_admin', 'merchant', 'store_manager'])) {
            abort(403, 'Unauthorized access to branch performance report.');
        }

        $tenantId = $this->getTenantId();
        $startDate = $request->input('start_date', date('Y-m-01'));
        $endDate = $request->input('end_date', date('Y-m-d'));

        $stores = Store::where('tenant_id', $tenantId)->get();

        $branchData = [];

        foreach ($stores as $store) {
            $ordersQuery = Order::where('tenant_id', $tenantId)
                ->where('store_id', $store->id)
                ->whereBetween('created_at', [$startDate.' 00:00:00', $endDate.' 23:59:59']);

            $totalOrders = (clone $ordersQuery)->count();
            $grossRevenue = (float) ((clone $ordersQuery)->sum('grand_total') ?? 0);
            $vatCollected = (float) ((clone $ordersQuery)->sum('tax_amount') ?? 0);

            $orderIds = (clone $ordersQuery)->pluck('id');

            $itemsSold = (int) OrderItem::whereIn('order_id', $orderIds)->sum('quantity');
            $cogs = (float) (OrderItem::whereIn('order_id', $orderIds)
                ->selectRaw('SUM(quantity * CASE WHEN cost_price > 0 THEN cost_price ELSE (SELECT purchase_cost FROM products WHERE products.id = order_items.product_id) END) as total_cogs')
                ->value('total_cogs') ?? 0);

            $stockMetrics = Stock::where('store_id', $store->id)
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

    public function recalculateDailySummary(Request $request)
    {
        $tenantId = $this->getTenantId();
        $validated = $request->validate([
            'store_id' => 'required|integer',
            'date' => 'required|date_format:Y-m-d',
        ]);

        $storeId = (int) $validated['store_id'];
        $date = $validated['date'];

        DailySalesSummary::recalculateDay($tenantId, $storeId, $date);

        // Resolve persistent database discrepancy alert
        DiscrepancyAlert::where('tenant_id', $tenantId)
            ->where('store_id', $storeId)
            ->where('date', $date)
            ->whereNull('resolved_at')
            ->update(['resolved_at' => now()]);

        return response()->json([
            'success' => true,
            'message' => "Daily sales summary recalculated successfully for store #{$storeId} on date {$date}.",
        ]);
    }
}
