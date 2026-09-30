<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\RegisterShift;
use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
        $storeId = (int) $request->input('store_id', auth()->user()->store_id ?? Store::where('tenant_id', $tenantId)->first()->id);

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

        // Verify store belongs to tenant
        $store = Store::where('id', $validated['store_id'])->where('tenant_id', $tenantId)->firstOrFail();
        $userId = auth()->id();

        // Close any lingering open shift
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

        return redirect()->back()->with('success', 'Register shift opened successfully!');
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
            ->firstOrFail();

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

        $expectedCash = $shift->opening_cash + $cashSales;
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

        return redirect()->back()->with([
            'success' => 'Shift closed & cash drawer reconciled!',
            'shiftSummary' => $shift,
        ]);
    }
}
