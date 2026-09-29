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

        return Inertia::render('Reports/ProfitLoss', [
            'startDate' => $startDate,
            'endDate' => $endDate,
            'totalSales' => (float) $totalSales,
            'cogs' => (float) $cogs,
            'grossProfit' => (float) $grossProfit,
            'totalExpenses' => (float) $totalExpenses,
            'netProfit' => (float) $netProfit,
        ]);
    }
}
