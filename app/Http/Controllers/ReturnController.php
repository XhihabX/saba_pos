<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Models\ProductReturn;
use App\Models\Stock;
use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ReturnController extends Controller
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

    public function index()
    {
        $tenantId = $this->getTenantId();
        $userStoreId = auth()->user()->store_id;

        $query = ProductReturn::whereHas('store', function ($q) use ($tenantId) {
            $q->where('tenant_id', $tenantId);
        })->with(['order', 'product']);

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
            'quantity' => 'required|numeric|min:1',
            'refund_amount' => 'required|numeric|min:0',
            'reason' => 'required|string',
        ]);

        return DB::transaction(function () use ($validated, $tenantId) {
            // Verify order belongs to user tenant
            $order = Order::where('invoice_no', $validated['invoice_no'])
                ->where('tenant_id', $tenantId)
                ->firstOrFail();

            // Verify product belongs to tenant
            $product = Product::where('id', $validated['product_id'])
                ->where('tenant_id', $tenantId)
                ->firstOrFail();

            $storeId = $order->store_id;

            $returnRecord = ProductReturn::create([
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
            $stock = Stock::firstOrCreate(
                ['store_id' => $storeId, 'product_id' => $product->id],
                ['quantity' => 0]
            );
            $stock->increment('quantity', $validated['quantity']);

            return redirect()->back()->with('success', 'Return & Cash Refund processed successfully!');
        });
    }
}
