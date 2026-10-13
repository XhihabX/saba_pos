<?php

namespace App\Http\Controllers;

use App\Mail\DiscrepancyAlertMail;
use App\Models\AuditLog;
use App\Models\DiscrepancyAlert;
use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\RegisterShift;
use App\Models\StockTransfer;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
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

    private function getStoreId(int $tenantId): int
    {
        $userStoreId = auth()->user()->store_id;
        if ($userStoreId) {
            return (int) $userStoreId;
        }

        $store = Store::where('tenant_id', $tenantId)->first();

        return $store ? (int) $store->id : 1;
    }

    public function dashboard(Request $request)
    {
        $tenantId = $this->getTenantId();
        $storeId = $this->getStoreId($tenantId);

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
        $storeId = $this->getStoreId($tenantId);

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
        $storeId = $this->getStoreId($tenantId);

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
        $storeId = $this->getStoreId($tenantId);

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
        $storeId = $this->getStoreId($tenantId);

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

        AuditLogger::log(
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

    public function mfsReconciliation(Request $request)
    {
        $tenantId = $this->getTenantId();
        $storeId = $this->getStoreId($tenantId);

        $mfsMethods = ['bkash', 'nagad', 'rocket', 'upay', 'mobile_wallet', 'mfs'];

        $query = OrderPayment::where('tenant_id', $tenantId)
            ->whereIn('payment_method', $mfsMethods)
            ->where(function ($q) {
                $q->where('verification_status', 'counter_verified')
                    ->orWhere('verification_status', 'auto_verified');
            })
            ->with(['order.user', 'order.store']);

        if (auth()->user()->role === 'store_manager') {
            $query->whereHas('order', fn ($q) => $q->where('store_id', $storeId));
        } elseif ($request->filled('store_id')) {
            $query->whereHas('order', fn ($q) => $q->where('store_id', (int) $request->input('store_id')));
        }

        if ($request->input('status') === 'unmatched') {
            $query->where('verification_status', 'counter_verified');
        } elseif ($request->input('status') === 'matched') {
            $query->where('verification_status', 'auto_verified');
        }

        $payments = $query->latest()->paginate(25)->withQueryString();

        // Check for 24h expired unmatched payments to generate discrepancy alerts and notifications
        $expiredUnmatched = OrderPayment::where('tenant_id', $tenantId)
            ->where('verification_status', 'counter_verified')
            ->where('created_at', '<=', now()->subHours(24))
            ->with(['order.store', 'order.user'])
            ->get();

        foreach ($expiredUnmatched as $unmatchedPayment) {
            $this->createMfsDiscrepancyAlertAndNotify($unmatchedPayment);
        }

        // Transform payment records to include matched/unmatched status and 24h expired indicator
        $transformedPayments = $payments->through(function ($payment) {
            $isMatched = ($payment->verification_status === 'auto_verified');
            $isExpired24h = (! $isMatched && $payment->created_at <= now()->subHours(24));

            return [
                'id' => $payment->id,
                'order_id' => $payment->order_id,
                'invoice_no' => $payment->order->invoice_no ?? ('ORD-'.$payment->order_id),
                'cashier_name' => $payment->order->user->name ?? 'Unknown Cashier',
                'store_name' => $payment->order->store->name ?? 'Main Outlet',
                'payment_method' => strtoupper($payment->payment_method),
                'trx_id' => $payment->reference_no ?? 'N/A',
                'amount' => (float) $payment->amount,
                'created_at' => $payment->created_at->format('Y-m-d H:i:s'),
                'time_ago' => $payment->created_at->diffForHumans(),
                'status' => $isMatched ? 'matched' : 'unmatched',
                'is_matched' => $isMatched,
                'is_unmatched_24h' => $isExpired24h,
            ];
        });

        $unmatchedCount = OrderPayment::where('tenant_id', $tenantId)
            ->where('verification_status', 'counter_verified')
            ->when(auth()->user()->role === 'store_manager', fn ($q) => $q->whereHas('order', fn ($sq) => $sq->where('store_id', $storeId)))
            ->count();

        $expired24hCount = OrderPayment::where('tenant_id', $tenantId)
            ->where('verification_status', 'counter_verified')
            ->where('created_at', '<=', now()->subHours(24))
            ->when(auth()->user()->role === 'store_manager', fn ($q) => $q->whereHas('order', fn ($sq) => $sq->where('store_id', $storeId)))
            ->count();

        return Inertia::render('Manager/MfsReconciliation', [
            'payments' => $transformedPayments,
            'unmatchedCount' => $unmatchedCount,
            'expired24hCount' => $expired24hCount,
            'filters' => $request->only(['status', 'store_id']),
        ]);
    }

    public function createMfsDiscrepancyAlertAndNotify(OrderPayment $payment): ?DiscrepancyAlert
    {
        $storeId = $payment->order->store_id ?? 1;
        $tenantId = $payment->tenant_id;
        $date = $payment->created_at->toDateString();
        $trxId = (string) $payment->reference_no;
        $amount = (float) $payment->amount;

        // Check if an alert already exists for this unmatched payment TrxID
        $existingAlert = DiscrepancyAlert::where('tenant_id', $tenantId)
            ->where('store_id', $storeId)
            ->where('expected->trx_id', $trxId)
            ->first();

        if ($existingAlert) {
            return $existingAlert;
        }

        $alert = DiscrepancyAlert::create([
            'tenant_id' => $tenantId,
            'store_id' => $storeId,
            'date' => $date,
            'expected' => [
                'type' => 'mfs_unmatched_24h',
                'trx_id' => $trxId,
                'amount' => $amount,
                'order_id' => $payment->order_id,
                'invoice_no' => $payment->order?->invoice_no,
                'cashier' => $payment->order->user->name ?? 'Cashier',
                'description' => "Unmatched counter-verified MFS payment ৳{$amount} (TrxID: {$trxId}) after 24h",
            ],
            'actual' => [
                'status' => 'unmatched',
                'verification_status' => 'counter_verified',
                'elapsed_hours' => round(now()->diffInHours($payment->created_at)),
            ],
        ]);

        // Email the owner
        try {
            $tenant = Tenant::find($tenantId);
            $ownerEmail = $tenant?->email;
            if (! $ownerEmail) {
                $owner = User::where('tenant_id', $tenantId)
                    ->whereIn('role', ['merchant', 'super_admin'])
                    ->first();
                $ownerEmail = $owner?->email;
            }

            if ($ownerEmail) {
                Mail::to($ownerEmail)->send(new DiscrepancyAlertMail($alert));
            }
        } catch (\Throwable $e) {
            Log::warning('Could not send MFS DiscrepancyAlertMail: '.$e->getMessage());
        }

        return $alert;
    }
}
