<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Stock;
use App\Models\StockTransfer;
use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class StockTransferController extends Controller
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
        $stores = Store::where('tenant_id', $tenantId)->get();
        $products = Product::where('tenant_id', $tenantId)->where('is_active', true)->get();

        $transfers = StockTransfer::whereHas('fromStore', function ($q) use ($tenantId) {
            $q->where('tenant_id', $tenantId);
        })
        ->with(['fromStore', 'toStore', 'items.product'])
        ->latest()
        ->paginate(15);

        return Inertia::render('Manager/Transfers', [
            'transfers' => $transfers,
            'stores' => $stores,
            'products' => $products,
        ]);
    }

    public function store(Request $request)
    {
        $tenantId = $this->getTenantId();

        $validated = $request->validate([
            'from_store_id' => 'required|exists:stores,id',
            'to_store_id' => 'required|exists:stores,id|different:from_store_id',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'notes' => 'nullable|string',
        ]);

        // Verify both stores belong to user tenant
        Store::where('id', $validated['from_store_id'])->where('tenant_id', $tenantId)->firstOrFail();
        Store::where('id', $validated['to_store_id'])->where('tenant_id', $tenantId)->firstOrFail();

        return DB::transaction(function () use ($validated, $tenantId) {
            // Pre-validate source store stock availability
            foreach ($validated['items'] as $item) {
                $product = Product::where('id', $item['product_id'])->where('tenant_id', $tenantId)->firstOrFail();
                $stock = Stock::where('store_id', $validated['from_store_id'])
                    ->where('product_id', $product->id)
                    ->first();
                $available = $stock ? $stock->quantity : 0;
                if ($available < $item['quantity']) {
                    throw ValidationException::withMessages([
                        'items' => ["Cannot transfer stock: Insufficient quantity for '{$product->name}' in source store. Available: {$available}, Requested: {$item['quantity']}."]
                    ]);
                }
            }

            $transfer = StockTransfer::create([
                'transfer_no' => 'TRF-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4)),
                'from_store_id' => $validated['from_store_id'],
                'to_store_id' => $validated['to_store_id'],
                'status' => 'completed',
                'notes' => $validated['notes'] ?? null,
            ]);

            foreach ($validated['items'] as $item) {
                // Ensure product belongs to tenant
                Product::where('id', $item['product_id'])->where('tenant_id', $tenantId)->firstOrFail();

                DB::table('stock_transfer_items')->insert([
                    'stock_transfer_id' => $transfer->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $fromStore = \App\Models\Store::find($validated['from_store_id']);
                $allowNegative = (bool) ($fromStore?->allow_negative_stock ?? false);

                // Deduct from Source Store with DB lock
                $fromStock = Stock::where('store_id', $validated['from_store_id'])
                    ->where('product_id', $item['product_id'])
                    ->lockForUpdate()
                    ->first();

                if (!$fromStock) {
                    $fromStock = Stock::create([
                        'tenant_id' => $tenantId,
                        'store_id' => $validated['from_store_id'],
                        'product_id' => $item['product_id'],
                        'quantity' => 0.00,
                    ]);
                }

                if (!$allowNegative && (float) $fromStock->quantity < $item['quantity']) {
                    $prodName = \App\Models\Product::find($item['product_id'])?->name ?? 'Product';
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'items' => ["Insufficient stock in origin store for '{$prodName}'. Available: {$fromStock->quantity}, requested: {$item['quantity']}."]
                    ]);
                }

                $fromStock->decrement('quantity', $item['quantity']);

                // Add to Target Store with DB lock
                $toStock = Stock::where('store_id', $validated['to_store_id'])
                    ->where('product_id', $item['product_id'])
                    ->lockForUpdate()
                    ->first();

                if (!$toStock) {
                    $toStock = Stock::create([
                        'tenant_id' => $tenantId,
                        'store_id' => $validated['to_store_id'],
                        'product_id' => $item['product_id'],
                        'quantity' => 0.00,
                    ]);
                }

                $toStock->increment('quantity', $item['quantity']);
            }

            return redirect()->back()->with('success', 'Inter-outlet stock transfer executed successfully!');
        });
    }
}
