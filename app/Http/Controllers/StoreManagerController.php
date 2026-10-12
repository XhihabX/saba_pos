<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Order;
use App\Models\RegisterShift;
use App\Models\StockTransfer;
use App\Models\Store;
use Illuminate\Http\Request;
use Inertia\Inertia;

class StoreManagerController extends Controller
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

    public function dashboard(Request $request)
    {
        $tenantId = $this->getTenantId();
        $storeId = auth()->user()->store_id ?? (Store::where('tenant_id', $tenantId)->first()?->id ?? 1);

        $todaySales = Order::where('tenant_id', $tenantId)
            ->where('store_id', $storeId)
            ->whereDate('created_at', date('Y-m-d'))
            ->sum('grand_total');

        $activeShifts = RegisterShift::where('tenant_id', $tenantId)
            ->where('store_id', $storeId)
            ->where('status', 'open')
            ->with('user')
            ->get();

        $recentTransfers = StockTransfer::where(function ($q) use ($storeId) {
            $q->where('from_store_id', $storeId)->orWhere('to_store_id', $storeId);
        })
            ->whereHas('fromStore', function ($q) use ($tenantId) {
                $q->where('tenant_id', $tenantId);
            })
            ->latest()
            ->take(5)
            ->get();

        $needsReviewCount = Order::where('tenant_id', $tenantId)
            ->where('store_id', $storeId)
            ->where('flagged_for_review', true)
            ->whereNull('resolved_at')
            ->count();

        return Inertia::render('Manager/Dashboard', [
            'todaySales' => (float) $todaySales,
            'activeShifts' => $activeShifts,
            'recentTransfers' => $recentTransfers,
            'needsReviewCount' => $needsReviewCount,
        ]);
    }

    public function shiftReconciliation(Request $request)
    {
        $tenantId = $this->getTenantId();
        $storeId = auth()->user()->store_id ?? (Store::where('tenant_id', $tenantId)->first()?->id ?? 1);

        $shifts = RegisterShift::where('tenant_id', $tenantId)
            ->where('store_id', $storeId)
            ->with(['user', 'store'])
            ->latest()
            ->paginate(15);

        return Inertia::render('Manager/Shifts', [
            'shifts' => $shifts,
        ]);
    }

    public function auditLogsIndex(Request $request)
    {
        $tenantId = $this->getTenantId();
        $storeId = auth()->user()->store_id ?? (Store::where('tenant_id', $tenantId)->first()?->id ?? 1);

        $query = AuditLog::where('tenant_id', $tenantId)
            ->where(function ($q) use ($storeId) {
                $q->where('store_id', $storeId)->orWhereNull('store_id');
            })
            ->with(['user', 'store']);

        if ($request->filled('action')) {
            $query->where('action', $request->input('action'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('description', 'LIKE', "%{$search}%")
                    ->orWhere('user_name', 'LIKE', "%{$search}%")
                    ->orWhere('action', 'LIKE', "%{$search}%");
            });
        }

        $logs = $query->latest()->paginate(25)->withQueryString();

        $stats = [
            'total_actions' => AuditLog::where('tenant_id', $tenantId)->where('store_id', $storeId)->count(),
            'pos_sales_count' => AuditLog::where('tenant_id', $tenantId)->where('store_id', $storeId)->where('action', 'pos_checkout')->count(),
            'shift_audits_count' => AuditLog::where('tenant_id', $tenantId)->where('store_id', $storeId)->whereIn('action', ['shift_opened', 'shift_closed'])->count(),
        ];

        return Inertia::render('Manager/AuditLogs', [
            'logs' => $logs,
            'stats' => $stats,
            'filters' => $request->only(['action', 'search']),
        ]);
    }

    public function needsReviewOrders(Request $request)
    {
        $tenantId = $this->getTenantId();
        $storeId = auth()->user()->store_id ?? (Store::where('tenant_id', $tenantId)->first()?->id ?? 1);

        $query = Order::where('tenant_id', $tenantId)
            ->where('flagged_for_review', true)
            ->with(['items.product', 'customer', 'user', 'store', 'resolver']);

        if (auth()->user()->role === 'store_manager') {
            $query->where('store_id', $storeId);
        } elseif ($request->filled('store_id')) {
            $query->where('store_id', (int) $request->input('store_id'));
        }

        if ($request->input('status') === 'resolved') {
            $query->whereNotNull('resolved_at');
        } elseif ($request->input('status') === 'all') {
            // no filter
        } else {
            // default: unresolved
            $query->whereNull('resolved_at');
        }

        $orders = $query->latest()->paginate(20)->withQueryString();

        $unresolvedCount = Order::where('tenant_id', $tenantId)
            ->when(auth()->user()->role === 'store_manager', fn ($q) => $q->where('store_id', $storeId))
            ->where('flagged_for_review', true)
            ->whereNull('resolved_at')
            ->count();

        return Inertia::render('Manager/NeedsReviewOrders', [
            'orders' => $orders,
            'unresolvedCount' => $unresolvedCount,
            'filters' => $request->only(['status', 'store_id']),
        ]);
    }

    public function resolveFlaggedOrder(Request $request, $id)
    {
        $tenantId = $this->getTenantId();
        $storeId = auth()->user()->store_id ?? (Store::where('tenant_id', $tenantId)->first()?->id ?? 1);

        $orderQuery = Order::where('tenant_id', $tenantId)
            ->where('id', $id)
            ->where('flagged_for_review', true);

        if (auth()->user()->role === 'store_manager') {
            $orderQuery->where('store_id', $storeId);
        }

        $order = $orderQuery->firstOrFail();

        $resolutionNotes = (string) $request->input('notes', 'Reviewed and resolved by manager');

        $order->resolved_at = now();
        $order->resolved_by = auth()->id();
        $order->notes = ($order->notes ? $order->notes.' | ' : '').'Resolution: '.$resolutionNotes;
        $order->save();

        \App\Services\AuditLogger::log(
            'order_review_resolved',
            "Order {$order->invoice_no} review resolved by ".auth()->user()->name,
            [
                'order_id' => $order->id,
                'invoice_no' => $order->invoice_no,
                'resolved_by' => auth()->id(),
                'resolution_notes' => $resolutionNotes,
            ],
            $order->store_id,
            $tenantId
        );

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => "Order {$order->invoice_no} marked as resolved.",
                'order' => $order,
            ]);
        }

        return redirect()->back()->with('success', "Order {$order->invoice_no} resolved successfully.");
    }
}
