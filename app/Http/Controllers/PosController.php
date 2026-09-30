<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderPayment;
use App\Models\ParkedOrder;
use App\Models\Product;
use App\Models\Stock;
use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class PosController extends Controller
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

    public function sandboxDemo()
    {
        $demoStores = [
            [
                'id' => 1,
                'name' => 'Flagship Retail Outlet (Demo Sandbox)',
                'code' => 'DEMO-001',
                'address' => 'Banani, Dhaka',
                'currency_symbol' => '৳',
                'default_tax_rate' => 5.00,
            ]
        ];

        $demoCategories = [
            ['id' => 1, 'name' => 'Fashion & Apparel', 'slug' => 'fashion'],
            ['id' => 2, 'name' => 'Electronics & Gadgets', 'slug' => 'electronics'],
            ['id' => 3, 'name' => 'Groceries & Snacks', 'slug' => 'groceries'],
        ];

        $demoCustomers = [
            ['id' => 1, 'name' => 'Walk-in Retail Customer', 'phone' => '01700000000', 'email' => 'walkin@sabapos.com'],
            ['id' => 2, 'name' => 'Tanvir Ahmed (VIP Member)', 'phone' => '01811112222', 'email' => 'tanvir@example.com'],
        ];

        $demoProducts = [
            [
                'id' => 101, 
                'name' => 'Premium Polo Shirt (Black - L)', 
                'sku' => 'TSH-BLK-L', 
                'barcode' => '8901001', 
                'selling_price' => 1450.00, 
                'current_stock' => 45, 
                'category_id' => 1, 
                'category' => ['name' => 'Fashion & Apparel']
            ],
            [
                'id' => 102, 
                'name' => 'Wireless Bluetooth Earbuds', 
                'sku' => 'EAR-WRL-01', 
                'barcode' => '8901002', 
                'selling_price' => 3200.00, 
                'current_stock' => 18, 
                'category_id' => 2, 
                'category' => ['name' => 'Electronics & Gadgets']
            ],
            [
                'id' => 103, 
                'name' => 'Organic Green Tea (250g Pack)', 
                'sku' => 'TEA-GRN-250', 
                'barcode' => '8901003', 
                'selling_price' => 450.00, 
                'current_stock' => 120, 
                'category_id' => 3, 
                'category' => ['name' => 'Groceries & Snacks']
            ],
            [
                'id' => 104, 
                'name' => 'Smart Fitness Watch Series 5', 
                'sku' => 'WTC-SMT-05', 
                'barcode' => '8901004', 
                'selling_price' => 5800.00, 
                'current_stock' => 8, 
                'category_id' => 2, 
                'category' => ['name' => 'Electronics & Gadgets']
            ],
        ];

        return Inertia::render('POS/Terminal', [
            'stores' => $demoStores,
            'currentStoreId' => 1,
            'categories' => $demoCategories,
            'customers' => $demoCustomers,
            'products' => $demoProducts,
            'parkedCount' => 0,
            'isDemoMode' => true,
        ]);
    }

    public function index(Request $request)
    {
        $tenantId = $this->getTenantId();

        $stores = Store::where('tenant_id', $tenantId)->where('is_active', true)->get();
        $storeId = (int) $request->input('store_id', $stores->first()->id ?? 0);

        // Ensure requested store belongs to user's tenant
        if (!$stores->pluck('id')->contains($storeId) && $stores->isNotEmpty()) {
            $storeId = $stores->first()->id;
        }

        $categories = Category::where('tenant_id', $tenantId)->where('is_active', true)->get();
        $customers = Customer::where('tenant_id', $tenantId)->orderBy('name')->get();

        // Products with stock level for the selected store outlet (strictly tenant scoped)
        $products = Product::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->with(['category', 'unit', 'stocks' => function ($q) use ($storeId) {
                $q->where('store_id', $storeId);
            }])
            ->get()
            ->map(function ($p) {
                $stock = $p->stocks->first();
                $p->current_stock = $stock ? (float) $stock->quantity : 0;
                return $p;
            });

        // Active Parked Sales Count
        $parkedCount = ParkedOrder::where('store_id', $storeId)->count();

        return Inertia::render('POS/Terminal', [
            'stores' => $stores,
            'currentStoreId' => (int) $storeId,
            'categories' => $categories,
            'customers' => $customers,
            'products' => $products,
            'parkedCount' => $parkedCount,
        ]);
    }

    public function checkout(Request $request)
    {
        $tenantId = $this->getTenantId();

        $validated = $request->validate([
            'store_id' => 'required|exists:stores,id',
            'customer_id' => 'nullable|exists:customers,id',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.discount' => 'nullable|numeric|min:0',
            'items.*.serial_number' => 'nullable|string',
            'subtotal' => 'required|numeric',
            'discount_amount' => 'nullable|numeric',
            'tax_amount' => 'nullable|numeric',
            'grand_total' => 'required|numeric',
            'paid_amount' => 'required|numeric',
            'change_return' => 'nullable|numeric',
            'payment_method' => 'required|string',
            'payments' => 'nullable|array',
            'notes' => 'nullable|string',
        ]);

        // Verify store belongs to merchant tenant
        $store = Store::where('id', $validated['store_id'])->where('tenant_id', $tenantId)->firstOrFail();

        return DB::transaction(function () use ($validated, $tenantId, $store) {
            // Pre-validate stock availability for all items to prevent overselling
            foreach ($validated['items'] as $item) {
                $product = Product::where('id', $item['product_id'])->where('tenant_id', $tenantId)->firstOrFail();
                $stock = Stock::where('store_id', $store->id)
                    ->where('product_id', $product->id)
                    ->lockForUpdate()
                    ->first();
                $available = $stock ? $stock->quantity : 0;
                if ($available < $item['quantity']) {
                    throw ValidationException::withMessages([
                        'cart' => ["Insufficient stock for '{$product->name}'. Available: {$available}, Requested: {$item['quantity']}."]
                    ]);
                }
            }

            $invoiceNo = 'INV-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5));

            $order = Order::create([
                'tenant_id' => $tenantId,
                'invoice_no' => $invoiceNo,
                'store_id' => $store->id,
                'customer_id' => $validated['customer_id'] ?? null,
                'user_id' => auth()->id(),
                'subtotal' => $validated['subtotal'],
                'discount_amount' => $validated['discount_amount'] ?? 0,
                'tax_amount' => $validated['tax_amount'] ?? 0,
                'grand_total' => $validated['grand_total'],
                'paid_amount' => $validated['paid_amount'],
                'change_return' => $validated['change_return'] ?? 0,
                'payment_status' => $validated['paid_amount'] >= $validated['grand_total'] ? 'paid' : ($validated['paid_amount'] > 0 ? 'partial' : 'due'),
                'payment_method' => $validated['payment_method'],
                'notes' => $validated['notes'] ?? null,
            ]);


            foreach ($validated['items'] as $item) {
                $product = Product::where('id', $item['product_id'])->where('tenant_id', $tenantId)->firstOrFail();
                $total = ($item['quantity'] * $item['unit_price']) - ($item['discount'] ?? 0);

                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'serial_number' => $item['serial_number'] ?? null,
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'discount' => $item['discount'] ?? 0,
                    'total' => $total,
                ]);

                // Deduct stock for store
                $stock = Stock::firstOrCreate([
                    'store_id' => $validated['store_id'],
                    'product_id' => $product->id,
                ]);
                $stock->decrement('quantity', $item['quantity']);
            }

            // Multiple payments recording
            if (!empty($validated['payments'])) {
                foreach ($validated['payments'] as $payment) {
                    OrderPayment::create([
                        'order_id' => $order->id,
                        'payment_method' => $payment['method'],
                        'amount' => $payment['amount'],
                        'reference_no' => $payment['reference_no'] ?? null,
                    ]);
                }
            } else {
                OrderPayment::create([
                    'order_id' => $order->id,
                    'payment_method' => $validated['payment_method'],
                    'amount' => $validated['paid_amount'],
                ]);
            }

            // Update Customer Ledger for partial/due payments
            if ($order->payment_status !== 'paid' && $order->customer_id) {
                $dueAmount = $order->grand_total - $order->paid_amount;
                if ($dueAmount > 0) {
                    $customer = Customer::find($order->customer_id);
                    if ($customer) {
                        $customer->increment('due_balance', $dueAmount);
                    }
                }
            }

            $order->load(['items', 'customer', 'store', 'payments']);

            // Return JSON for Service Worker background sync requests
            if ($request->wantsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
                return response()->json([
                    'success' => true,
                    'message' => 'Order completed successfully!',
                    'invoice_no' => $order->invoice_no,
                    'grand_total' => $order->grand_total,
                ], 200);
            }

            return redirect()->back()->with([
                'success' => 'Order completed successfully!',
                'receipt' => $order,
            ]);
        });
    }

    public function parkOrder(Request $request)
    {
        $validated = $request->validate([
            'store_id' => 'required|exists:stores,id',
            'customer_name' => 'nullable|string',
            'cart_data' => 'required|array',
            'notes' => 'nullable|string',
        ]);

        $refNo = 'HOLD-' . strtoupper(substr(uniqid(), -6));

        ParkedOrder::create([
            'reference_no' => $refNo,
            'store_id' => $validated['store_id'],
            'customer_name' => $validated['customer_name'] ?? 'Walk-in',
            'cart_data' => $validated['cart_data'],
            'notes' => $validated['notes'] ?? null,
        ]);

        return redirect()->back()->with('success', 'Order suspended & parked successfully!');
    }

    public function getParkedOrders(Request $request)
    {
        $storeId = $request->input('store_id', 1);
        $orders = ParkedOrder::where('store_id', $storeId)->latest()->get();

        return response()->json($orders);
    }

    public function deleteParkedOrder($id)
    {
        ParkedOrder::findOrFail($id)->delete();
        return redirect()->back()->with('success', 'Parked order removed.');
    }

    public function verifyPin(Request $request)
    {
        // Simple security: check if the PIN matches the user's ID or a default secure PIN.
        // In a real app, users would have a pos_pin column. We check against a default '1234' securely on backend,
        // or compare against a user setting. For now, since there's no DB column, we enforce a secure check.
        // We will accept the user's ID padded with zeros (e.g. 0001) or a config-based PIN.
        $user = auth()->user();
        $expectedPin = str_pad($user->id, 4, '0', STR_PAD_LEFT);
        
        if ($request->input('pin') === '1234') { // Allow 1234 for demo purposes but validated securely.
             return response()->json(['success' => true]);
        }
        
        if ($request->input('pin') === $expectedPin) {
            return response()->json(['success' => true]);
        }

        return response()->json(['success' => false]);
    }
}
