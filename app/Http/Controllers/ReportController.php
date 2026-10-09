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

        // 1. Total Gross Revenue (Tenant Scoped SQL Aggregation)
        $totalSales = (float) (Order::where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->sum('grand_total') ?? 0.00);

        // 2. Cost of Goods Sold (COGS) using stored order_items.cost_price via SQL Aggregation with PK JOIN to orders
        $cogs = (float) (OrderItem::where('order_items.tenant_id', $tenantId)
            ->join('orders', function($join) use ($tenantId, $startDate, $endDate) {
                $join->on('order_items.order_id', '=', 'orders.id')
                     ->where('orders.tenant_id', '=', $tenantId)
                     ->whereBetween('orders.created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
            })
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->selectRaw('SUM(order_items.quantity * COALESCE(NULLIF(order_items.cost_price, 0), products.purchase_cost, 0)) as total_cogs')
            ->value('total_cogs') ?? 0.00);

        // 3. Gross Profit = Sales - COGS
        $grossProfit = $totalSales - $cogs;

        // 4. Operating Expenses (Strict Tenant Scoped SQL Aggregation)
        $totalExpenses = (float) (Expense::where('tenant_id', $tenantId)
            ->whereBetween('date', [$startDate, $endDate])
            ->sum('amount') ?? 0.00);

        // 5. Net Profit = Gross Profit - Expenses
        $netProfit = $grossProfit - $totalExpenses;

        // 6. Sales Breakdown by Category via direct SQL JOIN Aggregation
        $salesByCategory = OrderItem::where('order_items.tenant_id', $tenantId)
            ->join('orders', function($join) use ($tenantId, $startDate, $endDate) {
                $join->on('order_items.order_id', '=', 'orders.id')
                     ->where('orders.tenant_id', '=', $tenantId)
                     ->whereBetween('orders.created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
            })
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->leftJoin('categories', 'products.category_id', '=', 'categories.id')
            ->selectRaw('COALESCE(categories.name, "Uncategorized") as category, SUM(order_items.quantity) as qty, SUM(order_items.total) as total')
            ->groupBy('categories.id', 'categories.name')
            ->get();

        // 7. Sales Breakdown by Payment Method via direct SQL JOIN Aggregation
        $salesByPayment = \App\Models\OrderPayment::join('orders', 'order_payments.order_id', '=', 'orders.id')
            ->where('orders.tenant_id', $tenantId)
            ->whereBetween('orders.created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->selectRaw('order_payments.payment_method, SUM(order_payments.amount) as total')
            ->groupBy('order_payments.payment_method')
            ->get()
            ->map(fn($row) => [
                'method' => ucfirst(str_replace('_', ' ', $row->payment_method)),
                'total' => (float) $row->total,
            ]);

        // 8. Sales Breakdown by Cashier Staff via SQL Aggregation
        $salesByCashier = Order::where('orders.tenant_id', $tenantId)
            ->whereBetween('orders.created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->leftJoin('users', 'orders.user_id', '=', 'users.id')
            ->selectRaw('COALESCE(users.name, "System Staff") as cashier, COUNT(orders.id) as order_count, SUM(orders.grand_total) as total_sales')
            ->groupBy('users.id', 'users.name')
            ->get();

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

        // SQL Aggregation for totals
        $totals = Order::where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->selectRaw('SUM(grand_total) as gross_sales, SUM(tax_amount) as gross_vat, SUM(subtotal) as gross_subtotal')
            ->first();

        $grossSales = (float) ($totals->gross_sales ?? 0.00);
        $grossVatCollected = (float) ($totals->gross_vat ?? 0.00);
        $grossSubtotal = (float) ($totals->gross_subtotal ?? 0.00);

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

        // Store breakdown via SQL aggregation
        $vatByStore = Order::where('orders.tenant_id', $tenantId)
            ->whereBetween('orders.created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->leftJoin('stores', 'orders.store_id', '=', 'stores.id')
            ->selectRaw('COALESCE(stores.name, "Default Outlet") as store_name, COALESCE(stores.bin_number, stores.vat_number, "") as bin_number, COUNT(orders.id) as order_count, SUM(orders.subtotal) as net_amount, SUM(orders.tax_amount) as vat_collected, SUM(orders.grand_total) as gross_total')
            ->groupBy('stores.id', 'stores.name', 'stores.bin_number', 'stores.vat_number')
            ->get();

        $orders = Order::where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->with(['store', 'customer'])
            ->orderBy('id', 'desc')
            ->paginate(50);

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

        $totalStockValue = (float) (\App\Models\Stock::join('products', 'stocks.product_id', '=', 'products.id')
            ->where('stocks.tenant_id', $tenantId)
            ->selectRaw('SUM(stocks.quantity * products.purchase_cost) as total_val')
            ->value('total_val') ?? 0.00);

        $totalPotentialRetailValue = (float) (\App\Models\Stock::join('products', 'stocks.product_id', '=', 'products.id')
            ->where('stocks.tenant_id', $tenantId)
            ->selectRaw('SUM(stocks.quantity * products.selling_price) as total_val')
            ->value('total_val') ?? 0.00);

        $productsCount = \App\Models\Product::where('tenant_id', $tenantId)->count();

        $products = \App\Models\Product::where('tenant_id', $tenantId)
            ->with(['category', 'stocks.store'])
            ->paginate(50);

        $batches = \App\Models\ProductBatch::where('tenant_id', $tenantId)
            ->with(['product', 'store'])
            ->where('quantity', '>', 0)
            ->orderBy('expiry_date', 'asc')
            ->paginate(50);

        $expiringSoon = \App\Models\ProductBatch::where('tenant_id', $tenantId)
            ->with(['product', 'store'])
            ->where('quantity', '>', 0)
            ->where('expiry_date', '<=', now()->addDays(30))
            ->orderBy('expiry_date', 'asc')
            ->get();

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

