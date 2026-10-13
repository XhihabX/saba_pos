<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\DailySalesSummary;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\ProductReturn;
use App\Models\RegisterShift;
use App\Models\Stock;
use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class ReturnController extends Controller
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

    public function index()
    {
        $tenantId = $this->getTenantId();
        $userStoreId = auth()->user()->store_id;

        $query = ProductReturn::where('tenant_id', $tenantId)->with(['order', 'product']);

        if ($userStoreId) {
            $query->where('store_id', $userStoreId);
        }

        $returns = $query->latest()->paginate(15);

        return Inertia::render('Manager/Returns', [
            'returns' => $returns,
        ]);
    }

    public function store(Request $request)
    {
        $tenantId = $this->getTenantId();

        $validated = $request->validate([
            'invoice_no' => 'required|string|exists:orders,invoice_no',
            'product_id' => 'required|exists:products,id',
            'variant_id' => 'nullable|exists:product_variants,id',
            'quantity' => 'required|numeric|min:0.01',
            'refund_amount' => 'required|numeric|min:0',
            'reason' => 'required|string',
            'supervisor_pin' => 'nullable|string',
        ]);

        return DB::transaction(function () use ($request, $validated, $tenantId) {
            $user = auth()->user();
            $approvedBy = $user ? $user->name : 'Store Manager';

            // Cashier requires supervisor PIN authorization
            if ($user && $user->role === 'cashier') {
                $pin = (string) ($request->input('supervisor_pin') ?? $request->input('pin') ?? '');
                if (empty($pin)) {
                    throw ValidationException::withMessages([
                        'supervisor_pin' => ['A valid supervisor PIN is required for cashier to process refunds.'],
                    ]);
                }

                $authorizedSupervisor = null;
                $supervisors = \App\Models\User::where('tenant_id', $tenantId)
                    ->whereIn('role', ['manager', 'store_manager', 'admin', 'merchant', 'super_admin'])
                    ->whereNotNull('pos_pin')
                    ->get();

                foreach ($supervisors as $sup) {
                    if (\Illuminate\Support\Facades\Hash::check($pin, $sup->pos_pin)) {
                        $authorizedSupervisor = $sup;
                        break;
                    }
                }

                if (! $authorizedSupervisor && ! empty($user->pos_pin) && \Illuminate\Support\Facades\Hash::check($pin, $user->pos_pin)) {
                    $authorizedSupervisor = $user;
                }

                if (! $authorizedSupervisor) {
                    throw ValidationException::withMessages([
                        'supervisor_pin' => ['Invalid supervisor PIN for refund authorization.'],
                    ]);
                }

                $approvedBy = $authorizedSupervisor->name.' (Supervisor PIN)';
            }

            // Verify order belongs to user tenant
            $order = Order::where('invoice_no', $validated['invoice_no'])
                ->where('tenant_id', $tenantId)
                ->lockForUpdate()
                ->firstOrFail();

            $storeId = (int) $order->store_id;

            // Verify product belongs to tenant
            $product = Product::where('id', $validated['product_id'])
                ->where('tenant_id', $tenantId)
                ->firstOrFail();

            // 1. Validate product & variant was part of original order
            $variantId = $validated['variant_id'] ?? null;
            $orderItemQuery = OrderItem::where('order_id', $order->id)
                ->where('product_id', $product->id);
            if ($variantId) {
                $orderItemQuery->where('variant_id', $variantId);
            }
            $orderItem = $orderItemQuery->first();

            if (! $orderItem) {
                throw ValidationException::withMessages([
                    'product_id' => ["Product '{$product->name}' was not part of original order invoice {$order->invoice_no}."],
                ]);
            }

            // 2. Validate quantity against already returned items
            $alreadyReturnedQuery = ProductReturn::where('order_id', $order->id)
                ->where('product_id', $product->id);
            if ($variantId) {
                $alreadyReturnedQuery->where('variant_id', $variantId);
            }
            $alreadyReturnedQty = (float) $alreadyReturnedQuery->sum('quantity');

            $maxReturnableQty = (float) $orderItem->quantity - $alreadyReturnedQty;
            if ($validated['quantity'] > $maxReturnableQty) {
                throw ValidationException::withMessages([
                    'quantity' => ["Return quantity ({$validated['quantity']}) exceeds maximum returnable quantity ({$maxReturnableQty})."],
                ]);
            }

            // Determine refund method: if order was unpaid/due, it is a due adjustment; otherwise cash or requested method
            $requestedRefundMethod = $request->input('refund_method');
            $refundMethod = 'cash';
            if (! empty($requestedRefundMethod)) {
                $refundMethod = strtolower(trim((string) $requestedRefundMethod));
            } elseif ($order->payment_status === 'due' || (float) $order->paid_amount <= 0.0) {
                $refundMethod = 'due_adjustment';
            }

            // 3. Cap refund amount: for due adjustments/store credit cap at item price; for cash refunds cap at actual paid cash
            $isExclusive = ($orderItem->vat_mode ?? 'exclusive') === 'exclusive';
            $lineGrossPaid = (float) $orderItem->total + ($isExclusive ? (float) $orderItem->vat_amount : 0.0);
            $itemPaidPrice = ($lineGrossPaid / (float) $orderItem->quantity) * (float) $validated['quantity'];
            $alreadyRefundedAmount = (float) ProductReturn::where('order_id', $order->id)->sum('refund_amount');

            $isDueOrCreditReturn = ($order->payment_status === 'due' || $refundMethod === 'due_adjustment' || $refundMethod === 'store_credit');
            if ($isDueOrCreditReturn) {
                $maxRefundableAmount = $itemPaidPrice;
            } else {
                $maxRefundableAmount = min($itemPaidPrice, max(0, (float) $order->paid_amount - $alreadyRefundedAmount));
            }

            if ((float) $validated['refund_amount'] > $maxRefundableAmount + 0.01) {
                throw ValidationException::withMessages([
                    'refund_amount' => ["Refund amount (৳{$validated['refund_amount']}) exceeds maximum returnable value for these items (৳".number_format($maxRefundableAmount, 2).').'],
                ]);
            }

            $returnRecord = ProductReturn::create([
                'tenant_id' => $tenantId,
                'order_id' => $order->id,
                'store_id' => $storeId,
                'product_id' => $product->id,
                'variant_id' => $orderItem->variant_id,
                'variant_name' => $orderItem->variant_name,
                'product_name' => $product->name,
                'quantity' => $validated['quantity'],
                'refund_amount' => $validated['refund_amount'],
                'refund_method' => $refundMethod,
                'reason' => $validated['reason'],
                'approved_by' => $approvedBy,
            ]);

            // Re-stock quantity back into Store Inventory for exact variant
            $stockQuery = Stock::where('store_id', $storeId)->where('product_id', $product->id);
            if ($orderItem->variant_id) {
                $stockQuery->where('variant_id', $orderItem->variant_id);
            } else {
                $stockQuery->whereNull('variant_id');
            }
            $stock = $stockQuery->lockForUpdate()->first();

            if (! $stock) {
                $stock = Stock::create([
                    'tenant_id' => $tenantId,
                    'store_id' => $storeId,
                    'product_id' => $product->id,
                    'variant_id' => $orderItem->variant_id,
                    'quantity' => 0,
                ]);
            }
            $stock->increment('quantity', $validated['quantity']);

            // Restock respecting batches
            $latestBatch = ProductBatch::where('tenant_id', $tenantId)
                ->where('store_id', $storeId)
                ->where('product_id', $product->id)
                ->orderBy('created_at', 'desc')
                ->lockForUpdate()
                ->first();

            if ($latestBatch) {
                $latestBatch->increment('quantity', $validated['quantity']);
            }

            // Adjust customer ledger and loyalty points
            if ($order->customer_id) {
                $customer = Customer::where('id', $order->customer_id)->where('tenant_id', $tenantId)->first();
                if ($customer) {
                    $pointsDeducted = floor((float) $validated['refund_amount'] / 100);
                    if ($pointsDeducted > 0 && (int) $customer->points > 0) {
                        $customer->decrement('points', min((int) $customer->points, (int) $pointsDeducted));
                    }
                    if ((float) $customer->due_balance > 0 && $order->payment_status !== 'paid') {
                        $dueReduction = min((float) $customer->due_balance, (float) $validated['refund_amount']);
                        $customer->decrement('due_balance', $dueReduction);
                    }
                }
            }

            // Record in active shift cash totals
            $activeShift = RegisterShift::where('tenant_id', $tenantId)
                ->where('store_id', $storeId)
                ->where('status', 'open')
                ->lockForUpdate()
                ->first();

            if ($refundMethod === 'cash' && $activeShift && Schema::hasColumn('register_shifts', 'total_refunds')) {
                $activeShift->increment('total_refunds', $validated['refund_amount']);
            }

            // Record in Daily Sales Summary
            DailySalesSummary::recordReturn(
                $tenantId,
                $storeId,
                now()->toDateString(),
                (float) $validated['refund_amount']
            );

            if ($request->wantsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
                return response()->json([
                    'success' => true,
                    'message' => 'Return & Cash Refund processed successfully!',
                    'return' => $returnRecord,
                ], 200);
            }

            return redirect()->back()->with('success', 'Return & Cash Refund processed successfully!');
        });
    }
}
