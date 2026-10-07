<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Stock;
use App\Models\Store;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class SupplierController extends Controller
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

    public function suppliersIndex()
    {
        $tenantId = $this->getTenantId();
        $suppliers = Supplier::where('tenant_id', $tenantId)->withCount('purchases')->latest()->get();

        return Inertia::render('Merchant/Suppliers', [
            'suppliers' => $suppliers,
        ]);
    }

    public function suppliersStore(Request $request)
    {
        $tenantId = $this->getTenantId();

        $validated = $request->validate([
            'company_name' => 'required|string|max:255',
            'contact_person' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'due_balance' => 'nullable|numeric|min:0',
        ]);

        $validated['tenant_id'] = $tenantId;

        Supplier::create($validated);

        return redirect()->back()->with('success', 'Supplier created successfully!');
    }

    public function updateSupplier(Request $request, $id)
    {
        $tenantId = $this->getTenantId();
        $supplier = Supplier::where('id', $id)->where('tenant_id', $tenantId)->firstOrFail();

        $validated = $request->validate([
            'company_name' => 'required|string|max:255',
            'contact_person' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'due_balance' => 'nullable|numeric|min:0',
        ]);

        $supplier->update($validated);

        return redirect()->back()->with('success', 'Supplier updated successfully!');
    }

    public function deleteSupplier($id)
    {
        $tenantId = $this->getTenantId();
        $supplier = Supplier::where('id', $id)->where('tenant_id', $tenantId)->firstOrFail();
        $supplier->delete();

        return redirect()->back()->with('success', 'Supplier deleted successfully!');
    }

    public function purchasesIndex()
    {
        $tenantId = $this->getTenantId();
        $stores = Store::where('tenant_id', $tenantId)->get();
        $suppliers = Supplier::where('tenant_id', $tenantId)->get();
        $products = Product::where('tenant_id', $tenantId)->where('is_active', true)->get();

        $purchases = Purchase::whereHas('store', function ($q) use ($tenantId) {
            $q->where('tenant_id', $tenantId);
        })->with(['store', 'supplier', 'items.product'])->latest()->paginate(15);

        return Inertia::render('Merchant/Purchases', [
            'purchases' => $purchases,
            'stores' => $stores,
            'suppliers' => $suppliers,
            'products' => $products,
        ]);
    }

    public function purchasesStore(Request $request)
    {
        $tenantId = $this->getTenantId();

        $validated = $request->validate([
            'store_id' => 'required|exists:stores,id',
            'supplier_id' => 'required|exists:suppliers,id',
            'paid_amount' => 'required|numeric|min:0',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_cost' => 'required|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        // Verify store and supplier belong to user tenant
        Store::where('id', $validated['store_id'])->where('tenant_id', $tenantId)->firstOrFail();
        Supplier::where('id', $validated['supplier_id'])->where('tenant_id', $tenantId)->firstOrFail();

        return DB::transaction(function () use ($validated, $tenantId) {
            $totalAmount = 0;
            foreach ($validated['items'] as $item) {
                // Ensure product belongs to tenant
                Product::where('id', $item['product_id'])->where('tenant_id', $tenantId)->firstOrFail();
                $totalAmount += $item['quantity'] * $item['unit_cost'];
            }

            $purchase = Purchase::create([
                'purchase_no' => 'PO-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4)),
                'store_id' => $validated['store_id'],
                'supplier_id' => $validated['supplier_id'],
                'total_amount' => $totalAmount,
                'paid_amount' => $validated['paid_amount'],
                'status' => 'received',
                'notes' => $validated['notes'] ?? null,
            ]);

            foreach ($validated['items'] as $item) {
                $totalCost = $item['quantity'] * $item['unit_cost'];
                PurchaseItem::create([
                    'purchase_id' => $purchase->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'unit_cost' => $item['unit_cost'],
                    'total_cost' => $totalCost,
                ]);

                // Update Stock in Store
                $stock = Stock::firstOrCreate(
                    ['store_id' => $validated['store_id'], 'product_id' => $item['product_id']],
                    ['quantity' => 0]
                );
                $stock->increment('quantity', $item['quantity']);
            }

            // Update Supplier Due Balance if unpaid portion exists
            $due = $totalAmount - $validated['paid_amount'];
            if ($due > 0) {
                Supplier::where('id', $validated['supplier_id'])->increment('due_balance', $due);
            }

            return redirect()->back()->with('success', 'Purchase Order recorded and inventory updated!');
        });
    }

    public function deletePurchase($id)
    {
        $tenantId = $this->getTenantId();
        $purchase = Purchase::whereHas('store', function ($q) use ($tenantId) {
            $q->where('tenant_id', $tenantId);
        })->with(['items', 'supplier'])->where('id', $id)->firstOrFail();

        return DB::transaction(function () use ($purchase) {
            foreach ($purchase->items as $item) {
                $stock = Stock::where('store_id', $purchase->store_id)
                    ->where('product_id', $item->product_id)
                    ->first();
                if ($stock) {
                    $stock->decrement('quantity', min($stock->quantity, $item->quantity));
                }
            }

            $due = max(0, $purchase->total_amount - $purchase->paid_amount);
            if ($due > 0 && $purchase->supplier) {
                $purchase->supplier->decrement('due_balance', min($purchase->supplier->due_balance, $due));
            }

            $purchase->delete();

            return redirect()->back()->with('success', 'Purchase Order deleted and store inventory adjusted.');
        });
    }
}
