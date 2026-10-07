<?php

namespace App\Http\Controllers;

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
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class ReturnController extends Controller
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
            'quantity' => 'required|numeric|min:0.01',
            'refund_amount' => 'required|numeric|min:0',
            'reason' => 'required|string',
        ]);

        return DB::transaction(function () use ($validated, $tenantId) {
            // Verify order belongs to user tenant
            $order = Order::where('invoice_no', $validated['invoice_no'])
                ->where('tenant_id', $tenantId)
                ->lockForUpdate()
                ->firstOrFail();

            // Verify product belongs to tenant
            $product = Product::where('id', $validated['product_id'])
                ->where('tenant_id', $tenantId)
                ->firstOrFail();

            // 1. Validate product was part of original order
            $orderItem = OrderItem::where('order_id', $order->id)
                ->where('product_id', $product->id)
                ->first();

            if (!$orderItem) {
                throw ValidationException::withMessages([
                    'product_id' => ["Product '{$product->name}' was not part of original order invoice {$order->invoice_no}."]
                ]);
            }

            // 2. Validate quantity against already returned items
            $alreadyReturnedQty = (float) ProductReturn::where('order_id', $order->id)
                ->where('product_id', $product->id)
                ->sum('quantity');

            $maxReturnableQty = (float) $orderItem->quantity - $alreadyReturnedQty;
            if ($validated['quantity'] > $maxReturnableQty) {
                throw ValidationException::withMessages([
                    'quantity' => ["Return quantity ({$validated['quantity']}) exceeds maximum returnable quantity ({$maxReturnableQty})."]
                ]);
            }

            // 3. Cap refund amount at paid price of returned items (including proportional VAT)
            $isExclusive = ($orderItem->vat_mode ?? 'exclusive') === 'exclusive';
            $lineGrossPaid = (float) $orderItem->total + ($isExclusive ? (float) $orderItem->vat_amount : 0.0);
            $itemPaidPrice = ($lineGrossPaid / (float) $orderItem->quantity) * (float) $validated['quantity'];
            $alreadyRefundedAmount = (float) ProductReturn::where('order_id', $order->id)->sum('refund_amount');
            $maxRefundableAmount = min($itemPaidPrice, max(0, (float) $order->paid_amount - $alreadyRefundedAmount));
            if ((float) $validated['refund_amount'] > $maxRefundableAmount + 0.01) {
                throw ValidationException::withMessages([
                    'refund_amount' => ["Refund amount (৳{$validated['refund_amount']}) exceeds maximum refundable paid price for these items (৳" . number_format($maxRefundableAmount, 2) . ")."]
                ]);
            }

            $storeId = $order->store_id;

            $returnRecord = ProductReturn::create([
                'tenant_id' => $tenantId,
                'order_id' => $order->id,
                'store_id' => $storeId,
                'product_id' => $product->id,
                'product_name' => $product->name,
                'quantity' => $validated['quantity'],
                'refund_amount' => $validated['refund_amount'],
                'reason' => $validated['reason'],
                'approved_by' => auth()->user()->name ?? 'Store Manager',
            ]);

            // Re-stock quantity back into Store Inventory
            $stock = Stock::where('store_id', $storeId)->where('product_id', $product->id)->lockForUpdate()->first();
            if (!$stock) {
                $stock = Stock::create([
                    'tenant_id' => $tenantId,
                    'store_id' => $storeId,
                    'product_id' => $product->id,
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
                $customer = \App\Models\Customer::where('id', $order->customer_id)->where('tenant_id', $tenantId)->first();
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

            if ($activeShift && \Illuminate\Support\Facades\Schema::hasColumn('register_shifts', 'total_refunds')) {
                $activeShift->increment('total_refunds', $validated['refund_amount']);
            }

            return redirect()->back()->with('success', 'Return & Cash Refund processed successfully!');
        });
    }
}
