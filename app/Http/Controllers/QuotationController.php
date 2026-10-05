<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class QuotationController extends Controller
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
        $quotations = Quotation::with(['customer', 'store'])
            ->where('tenant_id', $tenantId)
            ->latest()
            ->paginate(15);

        $customers = Customer::where('tenant_id', $tenantId)->get();
        $products = Product::where('tenant_id', $tenantId)->where('is_active', true)->get();

        return Inertia::render('Sales/Quotations', [
            'quotations' => $quotations,
            'customers' => $customers,
            'products' => $products,
        ]);
    }

    public function store(Request $request)
    {
        $tenantId = $this->getTenantId();

        $validated = $request->validate([
            'customer_id' => 'nullable|exists:customers,id',
            'subtotal' => 'required|numeric|min:0',
            'tax_amount' => 'required|numeric|min:0',
            'discount_amount' => 'required|numeric|min:0',
            'grand_total' => 'required|numeric|min:0',
            'items_json' => 'required|array',
            'valid_until' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        $user = auth()->user();
        $qNo = 'QT-' . strtoupper(substr(uniqid(), -6));
        $storeId = $user->store_id ?? (Store::where('tenant_id', $tenantId)->first()?->id ?? 1);

        Quotation::create([
            'tenant_id' => $tenantId,
            'store_id' => $storeId,
            'customer_id' => $validated['customer_id'] ?? null,
            'quotation_no' => $qNo,
            'status' => 'sent',
            'subtotal' => $validated['subtotal'],
            'tax_amount' => $validated['tax_amount'],
            'discount_amount' => $validated['discount_amount'],
            'grand_total' => $validated['grand_total'],
            'items_json' => json_encode($validated['items_json']),
            'valid_until' => $validated['valid_until'] ?? now()->addDays(14)->toDateString(),
            'notes' => $validated['notes'] ?? null,
        ]);

        return redirect()->back()->with('success', "Quotation {$qNo} created successfully!");
    }

    public function convertToOrder(Request $request, $id)
    {
        $tenantId = $this->getTenantId();
        $quotation = Quotation::where('id', $id)->where('tenant_id', $tenantId)->firstOrFail();

        if ($quotation->status === 'converted') {
            return redirect()->back()->with('error', 'Quotation has already been converted to an order.');
        }

        $items = json_decode($quotation->items_json, true) ?: [];

        return \Illuminate\Support\Facades\DB::transaction(function () use ($quotation, $items, $tenantId) {
            // Validate stock availability before conversion
            foreach ($items as $item) {
                $productId = $item['product_id'] ?? null;
                $qty = $item['quantity'] ?? 1;
                if ($productId) {
                    $product = Product::where('id', $productId)->where('tenant_id', $tenantId)->first();
                    if ($product) {
                        $stock = \App\Models\Stock::where('store_id', $quotation->store_id)
                            ->where('product_id', $product->id)
                            ->first();
                        $available = $stock ? $stock->quantity : 0;
                        if ($available < $qty) {
                            throw ValidationException::withMessages([
                                'stock' => ["Cannot convert quotation: Insufficient stock for '{$product->name}'. Available: {$available}, Requested: {$qty}."]
                            ]);
                        }
                    }
                }
            }

            $invoiceNo = 'INV-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5));

            $order = \App\Models\Order::create([
                'tenant_id' => $tenantId,
                'invoice_no' => $invoiceNo,
                'store_id' => $quotation->store_id,
                'customer_id' => $quotation->customer_id,
                'user_id' => auth()->id(),
                'subtotal' => $quotation->subtotal,
                'discount_amount' => $quotation->discount_amount,
                'tax_amount' => $quotation->tax_amount,
                'grand_total' => $quotation->grand_total,
                'paid_amount' => $quotation->grand_total,
                'change_return' => 0,
                'payment_status' => 'paid',
                'payment_method' => 'cash',
                'notes' => 'Converted from Quotation ' . $quotation->quotation_no,
            ]);

            foreach ($items as $item) {
                $productId = $item['product_id'] ?? null;
                if ($productId) {
                    $product = Product::where('id', $productId)->where('tenant_id', $tenantId)->first();
                    if ($product) {
                        \App\Models\OrderItem::create([
                            'order_id' => $order->id,
                            'product_id' => $product->id,
                            'product_name' => $product->name,
                            'quantity' => $item['quantity'] ?? 1,
                            'unit_price' => $item['unit_price'] ?? $product->selling_price,
                            'discount' => 0,
                            'total' => ($item['quantity'] ?? 1) * ($item['unit_price'] ?? $product->selling_price),
                        ]);

                        // Deduct stock
                        $stock = \App\Models\Stock::firstOrCreate(
                            ['store_id' => $quotation->store_id, 'product_id' => $product->id],
                            ['quantity' => 0]
                        );
                        $stock->decrement('quantity', $item['quantity'] ?? 1);
                    }
                }
            }

            \App\Models\OrderPayment::create([
                'order_id' => $order->id,
                'payment_method' => 'cash',
                'amount' => $quotation->grand_total,
            ]);

            $quotation->update(['status' => 'converted']);

            return redirect()->back()->with('success', "Quotation {$quotation->quotation_no} successfully converted to Live Order {$invoiceNo}!");
        });
    }

    public function deleteQuotation($id)
    {
        $tenantId = $this->getTenantId();
        $quotation = Quotation::where('id', $id)->where('tenant_id', $tenantId)->firstOrFail();
        $qNo = $quotation->quotation_no;
        $quotation->delete();

        return redirect()->back()->with('success', "Quotation {$qNo} deleted.");
    }
}
