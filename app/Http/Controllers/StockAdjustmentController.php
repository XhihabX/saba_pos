<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Stock;
use App\Models\StockAdjustment;
use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class StockAdjustmentController extends Controller
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

    public function index(Request $request)
    {
        $tenantId = $this->getTenantId();

        $adjustments = StockAdjustment::with(['product', 'store', 'user'])
            ->where('tenant_id', $tenantId)
            ->latest()
            ->paginate(15);

        $stores = Store::where('tenant_id', $tenantId)->where('is_active', true)->get();
        $products = Product::where('tenant_id', $tenantId)->where('is_active', true)->get();

        return Inertia::render('Inventory/Adjustments', [
            'adjustments' => $adjustments,
            'stores' => $stores,
            'products' => $products,
        ]);
    }

    public function store(Request $request)
    {
        $tenantId = $this->getTenantId();

        $validated = $request->validate([
            'store_id' => 'required|exists:stores,id',
            'product_id' => 'required|exists:products,id',
            'type' => 'required|in:write_off,damage,expired,stolen,audit_addition,audit_deduction',
            'quantity' => 'required|numeric|min:0.01',
            'notes' => 'nullable|string',
        ]);

        $store = Store::where('id', $validated['store_id'])->where('tenant_id', $tenantId)->firstOrFail();
        $product = Product::where('id', $validated['product_id'])->where('tenant_id', $tenantId)->firstOrFail();

        return DB::transaction(function () use ($validated, $tenantId, $store, $product) {
            $refNo = 'ADJ-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4));

            $adjustment = StockAdjustment::create([
                'tenant_id' => $tenantId,
                'store_id' => $store->id,
                'product_id' => $product->id,
                'user_id' => auth()->id(),
                'reference_no' => $refNo,
                'type' => $validated['type'],
                'quantity' => $validated['quantity'],
                'notes' => $validated['notes'] ?? null,
            ]);

            $stock = Stock::firstOrCreate([
                'store_id' => $store->id,
                'product_id' => $product->id,
            ]);

            if (in_array($validated['type'], ['write_off', 'damage', 'expired', 'stolen', 'audit_deduction'])) {
                $stock->decrement('quantity', $validated['quantity']);
            } else {
                $stock->increment('quantity', $validated['quantity']);
            }

            \App\Services\AuditLogger::log(
                'stock_adjusted',
                "Adjusted inventory stock for product '{$product->name}': {$validated['type']} {$validated['quantity']} units",
                [
                    'reference_no' => $refNo,
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'type' => $validated['type'],
                    'quantity' => $validated['quantity'],
                ],
                $store->id,
                $tenantId
            );

            return redirect()->back()->with('success', "Stock adjustment '{$refNo}' recorded successfully!");
        });
    }
}
