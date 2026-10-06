<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\RegisterShift;
use App\Models\Store;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ShiftController extends Controller
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

    public function currentShiftStatus(Request $request)
    {
        $userId = auth()->id();
        $tenantId = $this->getTenantId();
        $storeId = (int) $request->input('store_id', auth()->user()->store_id ?? (Store::where('tenant_id', $tenantId)->first()?->id ?? 1));

        $shift = RegisterShift::where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->where('store_id', $storeId)
            ->where('status', 'open')
            ->first();

        return response()->json([
            'isOpen' => !!$shift,
            'shift' => $shift,
        ]);
    }

    public function openShift(Request $request)
    {
        $tenantId = $this->getTenantId();

        $validated = $request->validate([
            'store_id' => 'required|exists:stores,id',
            'opening_cash' => 'required|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        // Verify store belongs to tenant or fallback to available tenant store
        $store = Store::where('id', $validated['store_id'])->where('tenant_id', $tenantId)->first();
        if (!$store) {
            $store = Store::where('tenant_id', $tenantId)->first() ?? Store::where('id', $validated['store_id'])->first() ?? Store::first();
        }

        if (!$store) {
            if ($request->wantsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest' || $request->acceptsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No valid store outlet found for shift opening.',
                ], 422);
            }
            return redirect()->back()->withErrors(['store_id' => 'No valid store outlet found for shift opening.']);
        }

        $userId = auth()->id();

        // Close any lingering open shift for this user
        RegisterShift::where('tenant_id', $tenantId)->where('user_id', $userId)->where('status', 'open')->update([
            'status' => 'closed',
            'closed_at' => now(),
        ]);

        $shift = RegisterShift::create([
            'tenant_id' => $tenantId,
            'store_id' => $store->id,
            'user_id' => $userId,
            'opening_cash' => $validated['opening_cash'],
            'status' => 'open',
            'opened_at' => now(),
            'notes' => $validated['notes'] ?? null,
        ]);

        AuditLogger::log(
            'shift_opened',
            "Opened register shift with initial float ৳{$shift->opening_cash}",
            ['opening_cash' => $shift->opening_cash, 'shift_id' => $shift->id],
            $store->id,
            $tenantId
        );

        if ($request->wantsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest' || $request->acceptsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Register shift opened successfully!',
                'shift' => $shift,
            ], 200);
        }

        return redirect()->back()->with(['success' => 'Register shift opened successfully!', 'shift' => $shift]);
    }

    public function closeShift(Request $request)
    {
        $tenantId = $this->getTenantId();

        $validated = $request->validate([
            'shift_id' => 'required|exists:register_shifts,id',
            'closing_cash_counted' => 'required|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        $shift = RegisterShift::where('id', $validated['shift_id'])
            ->where('tenant_id', $tenantId)
            ->first() ?? RegisterShift::where('id', $validated['shift_id'])->first();

        if (!$shift) {
            if ($request->wantsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest' || $request->acceptsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Active register shift record not found.',
                ], 404);
            }
            return redirect()->back()->withErrors(['shift_id' => 'Active register shift record not found.']);
        }

        // Calculate sales during this shift period, specific to the cashier (user_id), using OrderPayments for accurate splits.
        $cashSales = \App\Models\OrderPayment::whereHas('order', function($q) use ($shift) {
            $q->where('store_id', $shift->store_id)
              ->where('user_id', $shift->user_id)
              ->whereBetween('created_at', [$shift->opened_at, now()]);
        })->where('payment_method', 'cash')->sum('amount');

        $cardSales = \App\Models\OrderPayment::whereHas('order', function($q) use ($shift) {
            $q->where('store_id', $shift->store_id)
              ->where('user_id', $shift->user_id)
              ->whereBetween('created_at', [$shift->opened_at, now()]);
        })->where('payment_method', 'card')->sum('amount');

        $mobileSales = \App\Models\OrderPayment::whereHas('order', function($q) use ($shift) {
            $q->where('store_id', $shift->store_id)
              ->where('user_id', $shift->user_id)
              ->whereBetween('created_at', [$shift->opened_at, now()]);
        })->where('payment_method', 'mobile_wallet')->sum('amount');

        $totalChangeReturn = \App\Models\Order::where('store_id', $shift->store_id)
            ->where('user_id', $shift->user_id)
            ->whereBetween('created_at', [$shift->opened_at, now()])
            ->sum('change_return');

        $expectedCash = $shift->opening_cash + $cashSales - $totalChangeReturn;
        $cashDiff = $validated['closing_cash_counted'] - $expectedCash;

        $shift->update([
            'closing_cash_counted' => $validated['closing_cash_counted'],
            'total_cash_sales' => $cashSales,
            'total_card_sales' => $cardSales,
            'total_mobile_sales' => $mobileSales,
            'expected_cash' => $expectedCash,
            'cash_difference' => $cashDiff,
            'status' => 'closed',
            'closed_at' => now(),
            'notes' => $validated['notes'] ?? null,
        ]);

        AuditLogger::log(
            'shift_closed',
            "Closed register shift. Expected cash ৳{$expectedCash}, counted cash ৳{$validated['closing_cash_counted']}, difference ৳{$cashDiff}",
            [
                'shift_id' => $shift->id,
                'expected_cash' => $expectedCash,
                'closing_cash_counted' => $validated['closing_cash_counted'],
                'cash_difference' => $cashDiff,
                'cash_sales' => $cashSales,
                'card_sales' => $cardSales,
            ],
            $shift->store_id,
            $tenantId
        );

        if ($request->wantsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
            return response()->json([
                'success' => true,
                'message' => 'Shift closed & cash drawer reconciled!',
                'shiftSummary' => $shift,
            ], 200);
        }

        return redirect()->back()->with([
            'success' => 'Shift closed & cash drawer reconciled!',
            'shiftSummary' => $shift,
        ]);
    }

    /**
     * Render Day-End (Z) Report for Register Shift / Store Outlet
     */
    public function zReport($shiftId)
    {
        $shift = RegisterShift::with(['store', 'user'])->findOrFail($shiftId);

        $orders = \App\Models\Order::where('store_id', $shift->store_id)
            ->where('user_id', $shift->user_id)
            ->whereBetween('created_at', [$shift->opened_at, $shift->closed_at ?? now()])
            ->get();

        $zData = [
            'shift' => $shift,
            'totalOrders' => $orders->count(),
            'subtotal' => (float) $orders->sum('subtotal'),
            'discountTotal' => (float) $orders->sum('discount_amount'),
            'taxTotal' => (float) $orders->sum('tax_amount'),
            'grandTotal' => (float) $orders->sum('grand_total'),
            'cashSales' => (float) $shift->total_cash_sales,
            'cardSales' => (float) $shift->total_card_sales,
            'mobileSales' => (float) $shift->total_mobile_sales,
            'expectedCash' => (float) $shift->expected_cash,
            'actualCash' => (float) $shift->closing_cash_counted,
            'variance' => (float) $shift->cash_difference,
            'openedAt' => $shift->opened_at,
            'closedAt' => $shift->closed_at,
        ];

        return Inertia::render('Manager/ZReport', [
            'zData' => $zData,
        ]);
    }
}
