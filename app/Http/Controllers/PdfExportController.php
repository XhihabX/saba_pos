<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Stock;
use App\Models\Store;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class PdfExportController extends Controller
{
    public function downloadInvoice(Order $order)
    {
        $tenantId = auth()->user()->tenant_id;
        if ($order->tenant_id !== $tenantId) {
            abort(403, 'Unauthorized cross-tenant invoice access.');
        }

        $order->load(['store', 'customer', 'user', 'items.product', 'items.variant']);

        $pdf = Pdf::loadView('pdf.invoice', compact('order'));

        return $pdf->download("invoice-{$order->id}.pdf");
    }

    public function downloadMushak63(Order $order)
    {
        $tenantId = auth()->user()->tenant_id;
        if ($order->tenant_id !== $tenantId) {
            abort(403, 'Unauthorized cross-tenant tax invoice access.');
        }

        $order->load(['store', 'customer', 'user', 'items.product', 'items.variant']);

        $pdf = Pdf::loadView('pdf.mushak63', compact('order'));

        return $pdf->download("mushak63-{$order->id}.pdf");
    }

    public function downloadBranchReport(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;

        $stores = Store::where('tenant_id', $tenantId)->get();

        $branch_data = [];

        foreach ($stores as $store) {
            $ordersQuery = Order::where('tenant_id', $tenantId)->where('store_id', $store->id);

            $totalOrders = (clone $ordersQuery)->count();
            $grossRevenue = (clone $ordersQuery)->sum('grand_total');
            $vatCollected = (clone $ordersQuery)->sum('tax_amount');

            $orderIds = (clone $ordersQuery)->pluck('id');

            $itemsSold = OrderItem::whereIn('order_id', $orderIds)->sum('quantity');
            $cogs = OrderItem::whereIn('order_id', $orderIds)->selectRaw('SUM(cost_price * quantity) as total_cogs')->value('total_cogs') ?? 0;

            $totalStockQty = Stock::where('store_id', $store->id)->sum('quantity');

            $branch_data[] = [
                'store_id' => $store->id,
                'store_name' => $store->name,
                'total_orders' => $totalOrders,
                'items_sold' => $itemsSold,
                'gross_revenue' => $grossRevenue,
                'vat_collected' => $vatCollected,
                'cogs' => $cogs,
                'net_profit' => $grossRevenue - $cogs,
                'total_stock_qty' => $totalStockQty,
            ];
        }

        $pdf = Pdf::loadView('pdf.branch_report', compact('branch_data'));

        return $pdf->download('branch-report-'.date('Ymd-His').'.pdf');
    }
}
