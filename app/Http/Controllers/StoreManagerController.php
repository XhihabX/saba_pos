<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\RegisterShift;
use App\Models\Stock;
use App\Models\StockTransfer;
use App\Models\Store;
use Illuminate\Http\Request;
use Inertia\Inertia;

class StoreManagerController extends Controller
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
}
