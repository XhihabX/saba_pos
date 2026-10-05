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
            'items.*.discount' => 'nullable|numeric|min:0',
            'items.*.serial_number' => 'nullable|string',
            'discount_amount' => 'nullable|numeric|min:0',
            'paid_amount' => 'required|numeric|min:0',
            'payment_method' => 'required|string',
            'payments' => 'nullable|array',
            'notes' => 'nullable|string',
            'client_uuid' => 'nullable|string',
        ]);

        // Verify store belongs to merchant tenant
        $store = Store::where('id', $validated['store_id'])->where('tenant_id', $tenantId)->firstOrFail();

        // Idempotency check for offline sync retry
        if (!empty($validated['client_uuid'])) {
            $existing = Order::where('tenant_id', $tenantId)->where('notes', 'LIKE', '%[UUID:' . $validated['client_uuid'] . ']%')->first();
            if ($existing) {
                $existing->load(['items', 'customer', 'store', 'payments']);
                if ($request->wantsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
                    return response()->json([
                        'success' => true,
                        'message' => 'Order already processed (idempotent)',
                        'invoice_no' => $existing->invoice_no,
                        'grand_total' => $existing->grand_total,
                    ], 200);
                }
                return redirect()->back()->with(['success' => 'Order already processed!', 'receipt' => $existing]);
            }
        }

        return DB::transaction(function () use ($validated, $tenantId, $store) {
            $calculatedSubtotal = 0;
            $itemsToProcess = [];

            // Pre-validate stock & calculate authoritative server-side unit prices
            foreach ($validated['items'] as $item) {
                $product = Product::where('id', $item['product_id'])->where('tenant_id', $tenantId)->firstOrFail();
                $stock = Stock::where('store_id', $store->id)
                    ->where('product_id', $product->id)
                    ->lockForUpdate()
                    ->first();

                // Check store negative stock policy
                $available = $stock ? (float) $stock->quantity : 0;
                if (!$store->allow_negative_stock && $available < $item['quantity']) {
                    throw ValidationException::withMessages([
                        'cart' => ["Insufficient stock for '{$product->name}'. Available: {$available}, Requested: {$item['quantity']}."]
                    ]);
                }

                $unitPrice = (float) $product->selling_price;
                $lineDiscount = (float) ($item['discount'] ?? 0);
                $lineTotal = max(0, ($item['quantity'] * $unitPrice) - $lineDiscount);

                $calculatedSubtotal += $lineTotal;

                $itemsToProcess[] = [
                    'product' => $product,
                    'quantity' => $item['quantity'],
                    'unit_price' => $unitPrice,
                    'discount' => $lineDiscount,
                    'line_total' => $lineTotal,
                    'serial_number' => $item['serial_number'] ?? null,
                ];
            }

            $discountAmount = (float) ($validated['discount_amount'] ?? 0);
            $taxableSubtotal = max(0, $calculatedSubtotal - $discountAmount);
            $taxRate = (float) ($store->default_tax_rate ?? 0);
            $taxAmount = round(($taxableSubtotal * $taxRate) / 100, 2);
            $grandTotal = max(0, round($taxableSubtotal + $taxAmount, 2));

            $paidAmount = (float) $validated['paid_amount'];
            $changeReturn = max(0, round($paidAmount - $grandTotal, 2));
            $paymentStatus = $paidAmount >= $grandTotal ? 'paid' : ($paidAmount > 0 ? 'partial' : 'due');

            $invoiceNo = 'INV-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5));

            $notesText = $validated['notes'] ?? '';
            if (!empty($validated['client_uuid'])) {
                $notesText .= ' [UUID:' . $validated['client_uuid'] . ']';
            }

            $order = Order::create([
                'tenant_id' => $tenantId,
                'invoice_no' => $invoiceNo,
                'store_id' => $store->id,
                'customer_id' => $validated['customer_id'] ?? null,
                'user_id' => auth()->id(),
                'subtotal' => $calculatedSubtotal,
                'discount_amount' => $discountAmount,
                'tax_amount' => $taxAmount,
                'grand_total' => $grandTotal,
                'paid_amount' => $paidAmount,
                'change_return' => $changeReturn,
                'payment_status' => $paymentStatus,
                'payment_method' => $validated['payment_method'],
                'notes' => trim($notesText) ?: null,
            ]);

            foreach ($itemsToProcess as $item) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item['product']->id,
                    'product_name' => $item['product']->name,
                    'serial_number' => $item['serial_number'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'discount' => $item['discount'],
                    'total' => $item['line_total'],
                ]);

                // Deduct stock for store
                $stock = Stock::firstOrCreate([
                    'store_id' => $store->id,
                    'product_id' => $item['product']->id,
                ]);
                $stock->decrement('quantity', $item['quantity']);
            }

            // Multiple payments recording
            if (!empty($validated['payments'])) {
                foreach ($validated['payments'] as $payment) {
                    OrderPayment::create([
                        'order_id' => $order->id,
                        'payment_method' => $payment['method'],
                        'amount' => (float) $payment['amount'],
                        'reference_no' => $payment['reference_no'] ?? null,
                    ]);
                }
            } else {
                OrderPayment::create([
                    'order_id' => $order->id,
                    'payment_method' => $validated['payment_method'],
                    'amount' => $paidAmount,
                ]);
            }

            // Update Customer Ledger & Loyalty Points
            if ($order->customer_id) {
                $customer = Customer::find($order->customer_id);
                if ($customer) {
                    if ($paymentStatus !== 'paid') {
                        $dueAmount = max(0, $grandTotal - $paidAmount);
                        if ($dueAmount > 0) {
                            $customer->increment('due_balance', $dueAmount);
                        }
                    }
                    // Award 1 loyalty point per 100 currency units spent
                    $earnedPoints = floor($grandTotal / 100);
                    if ($earnedPoints > 0) {
                        $customer->increment('points', $earnedPoints);
                    }
                }
            }

            $order->load(['items', 'customer', 'store', 'payments']);

            if (request()->wantsJson() || request()->header('X-Requested-With') === 'XMLHttpRequest') {
                return response()->json([
                    'success' => true,
                    'message' => 'Order completed successfully!',
                    'invoice_no' => $order->invoice_no,
                    'grand_total' => $order->grand_total,
                    'order' => $order,
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
        $tenantId = $this->getTenantId();

        $validated = $request->validate([
            'store_id' => 'required|exists:stores,id',
            'customer_name' => 'nullable|string',
            'cart_data' => 'required|array',
            'notes' => 'nullable|string',
        ]);

        $refNo = 'HOLD-' . strtoupper(substr(uniqid(), -6));

        ParkedOrder::create([
            'tenant_id' => $tenantId,
            'reference_no' => $refNo,
            'store_id' => $validated['store_id'],
            'customer_name' => $validated['customer_name'] ?? 'Walk-in Customer',
            'cart_data' => $validated['cart_data'],
            'notes' => $validated['notes'] ?? null,
        ]);

        return redirect()->back()->with('success', 'Order suspended & parked successfully!');
    }

    public function getParkedOrders(Request $request)
    {
        $tenantId = $this->getTenantId();
        $storeId = (int) $request->input('store_id', 1);

        $orders = ParkedOrder::where('tenant_id', $tenantId)
            ->where('store_id', $storeId)
            ->latest()
            ->get();

        return response()->json($orders);
    }

    public function deleteParkedOrder($id)
    {
        $tenantId = $this->getTenantId();
        ParkedOrder::where('id', $id)->where('tenant_id', $tenantId)->firstOrFail()->delete();

        return redirect()->back()->with('success', 'Parked order removed.');
    }

    public function verifyPin(Request $request)
    {
        $user = auth()->user();
        $pin = (string) $request->input('pin', '');

        if (empty($pin)) {
            return response()->json(['success' => false, 'message' => 'PIN is required.'], 422);
        }

        // 1. Check user pos_pin column if configured
        if (!empty($user->pos_pin)) {
            if ($user->pos_pin === $pin || \Illuminate\Support\Facades\Hash::check($pin, $user->pos_pin)) {
                return response()->json(['success' => true]);
            }
        }

        // 2. Check user ID padded PIN (e.g. user ID 1 => 0001) or cashier role check
        $expectedPin = str_pad((string) $user->id, 4, '0', STR_PAD_LEFT);
        if ($pin === $expectedPin) {
            return response()->json(['success' => true]);
        }

        // 3. Compare with account password as fallback security check
        if (\Illuminate\Support\Facades\Hash::check($pin, $user->password)) {
            return response()->json(['success' => true]);
        }

        return response()->json(['success' => false, 'message' => 'Invalid Cashier PIN.'], 403);
    }
}
