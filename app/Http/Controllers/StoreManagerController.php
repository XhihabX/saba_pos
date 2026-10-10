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

        return Inertia::render('Manager/Dashboard', [
            'todaySales' => (float) $todaySales,
            'activeShifts' => $activeShifts,
            'recentTransfers' => $recentTransfers,
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
}
