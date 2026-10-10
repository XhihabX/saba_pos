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
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
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
        if (!$user->tenant_id) {
            abort(403, 'User does not belong to any tenant');
        }
        return $user->tenant_id;
    }

    public function sandboxDemo()
    {
        if (auth()->check()) {
            return redirect()->route('pos.index');
        }
        return redirect()->route('login')->with('info', 'Please log in with your cashier or merchant credentials to access the POS Workstation.');
    }

    public function index(Request $request)
    {
        $tenantId = $this->getTenantId();

        // 1. Stores (Tenant Scoped with Caching)
        $stores = Cache::remember("pos_stores_{$tenantId}", 60, function () use ($tenantId) {
            $s = Store::where('tenant_id', $tenantId)->where('is_active', true)->get();
            if ($s->isEmpty()) {
                $s = Store::where('tenant_id', $tenantId)->get();
            }
            if ($s->isEmpty()) {
                try {
                    $defaultStore = Store::firstOrCreate([
                        'tenant_id' => $tenantId,
                        'name' => 'Main Outlet',
                    ], [
                        'code' => 'STORE-001',
                        'currency_symbol' => '৳',
                        'default_tax_rate' => 15.00,
                        'is_active' => true,
                    ]);
                    $s = collect([$defaultStore]);
                } catch (\Throwable $e) {
                    $s = collect([]);
                }
            }
            return $s->toArray();
        });

        $firstStoreId = !empty($stores) ? ($stores[0]['id'] ?? 1) : 1;
        $storeId = (int) $request->input('store_id', $firstStoreId);

        // 2. Categories (Tenant Scoped with Caching)
        $categories = Cache::remember("pos_categories_{$tenantId}", 60, function () use ($tenantId) {
            return Category::where('tenant_id', $tenantId)->where('is_active', true)->get()->toArray();
        });

        // 3. Customers (Limit 50 with Caching for High-Capacity Scaling)
        $customers = Cache::remember("pos_initial_customers_{$tenantId}", 60, function () use ($tenantId) {
            $c = Customer::where('tenant_id', $tenantId)->orderBy('name')->limit(50)->get();
            if ($c->isEmpty()) {
                try {
                    $walkIn = Customer::firstOrCreate(
                        ['tenant_id' => $tenantId, 'name' => 'Walk-in Customer'],
                        ['phone' => '0000000000', 'address' => 'Store Counter', 'due_balance' => 0.00]
                    );
                    $c = collect([$walkIn]);
                } catch (\Throwable $e) {
                    $c = collect([]);
                }
            }
            return $c->toArray();
        });

        // 4. Products (Paginated / Limited initial load for high-capacity catalog scaling)
        $products = Cache::remember("pos_initial_products_{$tenantId}_{$storeId}", 60, function () use ($tenantId, $storeId) {
            return Product::where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->select(['id', 'tenant_id', 'category_id', 'unit_id', 'name', 'sku', 'barcode', 'selling_price', 'vat_rate', 'vat_mode', 'is_active'])
                ->with(['category:id,name', 'unit:id,name', 'stocks' => function ($sq) use ($storeId) {
                    $sq->select(['id', 'store_id', 'product_id', 'quantity'])->where('store_id', $storeId);
                }])
                ->limit(50)
                ->get()
                ->map(function ($p) {
                    $stock = $p->stocks ? $p->stocks->first() : null;
                    return [
                        'id' => $p->id,
                        'name' => $p->name,
                        'sku' => $p->sku,
                        'barcode' => $p->barcode,
                        'selling_price' => (float) $p->selling_price,
                        'vat_rate' => (float) $p->vat_rate,
                        'vat_mode' => $p->vat_mode,
                        'category' => $p->category ? ['id' => $p->category->id, 'name' => $p->category->name] : null,
                        'unit' => $p->unit ? ['id' => $p->unit->id, 'name' => $p->unit->name] : null,
                        'current_stock' => $stock ? (float) $stock->quantity : 0.0,
                    ];
                })
                ->toArray();
        });

        // 5. Active Shift Check
        $activeShift = \App\Models\RegisterShift::where('tenant_id', $tenantId)
            ->where('user_id', auth()->id())
            ->where('store_id', $storeId)
            ->where('status', 'open')
            ->first();

        $activeShiftData = $activeShift ? [
            'id' => $activeShift->id,
            'opening_float' => (float) $activeShift->opening_float,
            'opened_at' => (string) $activeShift->opened_at,
            'status' => $activeShift->status,
        ] : null;

        return Inertia::render('POS/Terminal', [
            'stores' => $stores,
            'categories' => $categories,
            'customers' => $customers,
            'initialProducts' => $products,
            'activeStoreId' => $storeId,
            'activeShift' => $activeShiftData,
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
            'items.*.variant_id' => 'nullable|exists:product_variants,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.discount' => 'nullable|numeric|min:0',
            'items.*.serial_number' => 'nullable|string',
            'discount_amount' => 'nullable|numeric|min:0',
            'paid_amount' => 'required|numeric|min:0',
            'payment_method' => 'required|string',
            'payments' => 'nullable|array',
            'notes' => 'nullable|string',
            'client_uuid' => 'required|string',
            'idempotency_key' => 'nullable|string',
            'supervisor_pin' => 'nullable|string',
        ]);


        $store = Store::where('id', $validated['store_id'])->where('tenant_id', $tenantId)->first();
        if (!$store) {
            throw ValidationException::withMessages([
                'store_id' => ['Selected store outlet is invalid or belongs to another tenant.']
            ]);
        }

        // VAT registered store BIN requirement check
        if ($store->is_vat_registered && empty(trim((string) ($store->bin_number ?? $store->vat_number ?? '')))) {
            throw ValidationException::withMessages([
                'store_id' => ['Store is flagged as VAT registered but BIN Number is missing. Please configure BIN number in Store Settings before issuing fiscal invoices.']
            ]);
        }

        // Validate tender lines sum to paid amount
        $paidAmount = (float) $validated['paid_amount'];
        if (!empty($validated['payments'])) {
            $sumPayments = (float) array_sum(array_column($validated['payments'], 'amount'));
            if (abs($sumPayments - $paidAmount) > 0.01) {
                throw ValidationException::withMessages([
                    'payments' => ["Sum of payment tender lines (৳" . number_format($sumPayments, 2) . ") does not match total paid amount (৳" . number_format($paidAmount, 2) . ")."]
                ]);
            }
        }

        // Validate line item discounts do not exceed line gross
        foreach ($validated['items'] as $item) {
            $prodCheck = Product::where('id', $item['product_id'])->where('tenant_id', $tenantId)->first();
            if ($prodCheck) {
                $lineGross = (float) $item['quantity'] * (float) $prodCheck->selling_price;
                $lineDisc = (float) ($item['discount'] ?? 0);
                if ($lineDisc > $lineGross + 0.001) {
                    throw ValidationException::withMessages([
                        'discount' => ["Line discount (৳" . number_format($lineDisc, 2) . ") cannot exceed item price (৳" . number_format($lineGross, 2) . ")."]
                    ]);
                }
            }
        }

        // Server-side discount limit & supervisor PIN enforcement
        $discountAmount = (float) ($validated['discount_amount'] ?? 0);
        $hasLineDiscounts = false;
        foreach ($validated['items'] as $item) {
            if (!empty($item['discount']) && (float) $item['discount'] > 0) {
                $hasLineDiscounts = true;
                break;
            }
        }

        if ($discountAmount > 0 || $hasLineDiscounts) {
            $supervisorPin = (string) ($request->input('supervisor_pin') ?? $request->input('pin') ?? '');
            $user = auth()->user();
            if (empty($supervisorPin) || empty($user->pos_pin) || !Hash::check($supervisorPin, $user->pos_pin)) {
                throw ValidationException::withMessages([
                    'discount' => ['A valid supervisor PIN is required to apply cart or line item discounts.']
                ]);
            }
        }

        // Active register shift requirement
        $activeShift = \App\Models\RegisterShift::where('tenant_id', $tenantId)
            ->where('user_id', auth()->id())
            ->where('store_id', $store->id)
            ->where('status', 'open')
            ->first();

        if (!$activeShift) {
            throw ValidationException::withMessages([
                'shift' => ['An active register shift must be opened before processing sales checkout.']
            ]);
        }

        return DB::transaction(function () use ($request, $validated, $tenantId, $store, $paidAmount, $discountAmount) {
            // Idempotency check inside transaction using orders.idempotency_key
            $idempotencyKey = $validated['idempotency_key'] ?? $validated['client_uuid'] ?? null;
            if (!empty($idempotencyKey)) {
                $existing = Order::where('tenant_id', $tenantId)
                    ->where('idempotency_key', $idempotencyKey)
                    ->lockForUpdate()
                    ->first();

                if ($existing) {
                    $existing->load(['items', 'customer', 'store', 'payments', 'user']);
                    if ($request->wantsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
                        return response()->json([
                            'success' => true,
                            'message' => 'Order already processed (idempotent)',
                            'invoice_no' => $existing->invoice_no,
                            'grand_total' => $existing->grand_total,
                            'order' => $existing,
                        ], 200);
                    }
                    return redirect()->back()->with(['success' => 'Order already processed!', 'receipt' => $existing]);
                }
            }

            $calculatedSubtotalPaisa = 0;
            $totalInvoiceVatPaisa = 0;
            $itemsToProcess = [];
            $hasExclusiveItems = false;

            foreach ($validated['items'] as $item) {
                $product = Product::where('id', $item['product_id'])
                    ->where('tenant_id', $tenantId)
                    ->lockForUpdate()
                    ->firstOrFail();

                $variant = null;
                if (!empty($item['variant_id'])) {
                    $variant = \App\Models\ProductVariant::where('id', $item['variant_id'])
                        ->where('product_id', $product->id)
                        ->where('tenant_id', $tenantId)
                        ->first();
                }

                $stockQuery = Stock::where('store_id', $store->id)
                    ->where('product_id', $product->id);
                if ($variant) {
                    $stockQuery->where('variant_id', $variant->id);
                } else {
                    $stockQuery->whereNull('variant_id');
                }
                $stock = $stockQuery->lockForUpdate()->first();

                if (!$stock) {
                    $stock = Stock::create([
                        'tenant_id' => $tenantId,
                        'store_id' => $store->id,
                        'product_id' => $product->id,
                        'variant_id' => $variant?->id,
                        'quantity' => 0.00,
                    ]);
                }

                $available = (float) $stock->quantity;
                $allowNegative = (bool) ($store->allow_negative_stock ?? false);

                $itemName = $product->name . ($variant ? " ({$variant->name})" : "");

                if (!$allowNegative && $available < $item['quantity']) {
                    throw ValidationException::withMessages([
                        'cart' => ["Insufficient stock for '{$itemName}'. Available: {$available}, Requested: {$item['quantity']}."]
                    ]);
                }

                $sellingPrice = $variant?->price ?? $product->selling_price;
                $costPrice = $variant?->cost_price ?? $product->purchase_cost ?? 0.00;

                $unitPricePaisa = intval(round((float) $sellingPrice * 100));
                $lineDiscountPaisa = intval(round((float) ($item['discount'] ?? 0) * 100));
                $lineNetPaisa = max(0, intval(round($item['quantity'] * $unitPricePaisa)) - $lineDiscountPaisa);

                $vatRate = (float) ($product->vat_rate ?? $store->default_tax_rate ?? 15.0);
                $vatMode = $product->vat_mode ?? $store->vat_mode ?? 'exclusive';

                if ($vatMode === 'inclusive') {
                    $itemVatPaisa = intval(round(($lineNetPaisa * $vatRate) / (100.0 + $vatRate)));
                    $lineTotalPaisa = $lineNetPaisa;
                } else {
                    $itemVatPaisa = intval(round(($lineNetPaisa * $vatRate) / 100.0));
                    $lineTotalPaisa = $lineNetPaisa;
                    if ($vatRate > 0) {
                        $hasExclusiveItems = true;
                    }
                }

                $calculatedSubtotalPaisa += $lineTotalPaisa;
                $totalInvoiceVatPaisa += $itemVatPaisa;
                $calculatedCogsPaisa = ($calculatedCogsPaisa ?? 0) + intval(round($item['quantity'] * (float) $costPrice * 100));

                $itemsToProcess[] = [
                    'product' => $product,
                    'variant' => $variant,
                    'stock' => $stock,
                    'quantity' => $item['quantity'],
                    'unit_price' => $unitPricePaisa / 100,
                    'cost_price' => (float) $costPrice,
                    'discount' => $lineDiscountPaisa / 100,
                    'vat_rate' => $vatRate,
                    'vat_amount' => $itemVatPaisa / 100,
                    'vat_mode' => $vatMode,
                    'line_total' => $lineTotalPaisa / 100,
                    'serial_number' => $item['serial_number'] ?? null,
                ];
            }


            $calculatedSubtotal = $calculatedSubtotalPaisa / 100;
            $calculatedCogs = ($calculatedCogsPaisa ?? 0) / 100;
            if ($discountAmount > $calculatedSubtotal + 0.001) {
                throw ValidationException::withMessages([
                    'discount_amount' => ['Order discount amount cannot exceed calculated subtotal.']
                ]);
            }

            $discountPaisa = intval(round($discountAmount * 100));
            if ($discountPaisa > 0 && $calculatedSubtotalPaisa > 0) {
                $taxableSubtotalPaisa = max(0, $calculatedSubtotalPaisa - $discountPaisa);
                $totalInvoiceVatPaisa = intval(round($totalInvoiceVatPaisa * ($taxableSubtotalPaisa / $calculatedSubtotalPaisa)));
            } else {
                $taxableSubtotalPaisa = $calculatedSubtotalPaisa;
            }

            if ($hasExclusiveItems) {
                $grandTotalPaisa = max(0, $taxableSubtotalPaisa + $totalInvoiceVatPaisa);
            } else {
                $grandTotalPaisa = max(0, $taxableSubtotalPaisa);
            }

            $paidPaisa = intval(round($paidAmount * 100));
            $changeReturnPaisa = max(0, $paidPaisa - $grandTotalPaisa);

            $grandTotal = $grandTotalPaisa / 100;
            $paymentStatus = $paidAmount >= $grandTotal ? 'paid' : ($paidAmount > 0 ? 'partial' : 'due');

            $invoiceNo = 'INV-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5));

            $order = Order::create([
                'tenant_id' => $tenantId,
                'idempotency_key' => $idempotencyKey,
                'invoice_no' => $invoiceNo,
                'store_id' => $store->id,
                'customer_id' => $validated['customer_id'] ?? null,
                'user_id' => auth()->id(),
                'subtotal' => $calculatedSubtotal,
                'discount_amount' => $discountPaisa / 100,
                'tax_amount' => $totalInvoiceVatPaisa / 100,
                'grand_total' => $grandTotal,
                'cogs' => $calculatedCogs,
                'paid_amount' => $paidPaisa / 100,
                'change_return' => $changeReturnPaisa / 100,
                'payment_status' => $paymentStatus,
                'payment_method' => $validated['payment_method'],
                'notes' => $validated['notes'] ?? null,
            ]);

            foreach ($itemsToProcess as $item) {
                OrderItem::create([
                    'tenant_id' => $tenantId,
                    'order_id' => $order->id,
                    'product_id' => $item['product']->id,
                    'variant_id' => $item['variant']?->id,
                    'variant_name' => $item['variant']?->name,
                    'product_name' => $item['product']->name,
                    'serial_number' => $item['serial_number'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'cost_price' => $item['cost_price'],
                    'discount' => $item['discount'],
                    'vat_rate' => $item['vat_rate'],
                    'vat_amount' => $item['vat_amount'],
                    'total' => $item['line_total'],
                ]);


                // Deduct stock
                $item['stock']->decrement('quantity', $item['quantity']);

                // FEFO Batch Stock Deduction
                $batches = \App\Models\ProductBatch::where('tenant_id', $tenantId)
                    ->where('store_id', $store->id)
                    ->where('product_id', $item['product']->id)
                    ->where('quantity', '>', 0)
                    ->orderBy('expiry_date', 'asc')
                    ->lockForUpdate()
                    ->get();

                $neededQty = (float) $item['quantity'];
                foreach ($batches as $batch) {
                    if ($neededQty <= 0) break;
                    $deduct = min((float) $batch->quantity, $neededQty);
                    $batch->decrement('quantity', $deduct);
                    $neededQty -= $deduct;
                }
            }

            // Multiple payments & MFS Claiming with row locking
            if (!empty($validated['payments'])) {
                foreach ($validated['payments'] as $payment) {
                    OrderPayment::create([
                        'tenant_id' => $tenantId,
                        'order_id' => $order->id,
                        'payment_method' => $payment['method'],
                        'amount' => (float) $payment['amount'],
                        'reference_no' => $payment['reference_no'] ?? null,
                    ]);

                    if (in_array(strtolower($payment['method']), ['bkash', 'nagad', 'rocket', 'upay']) || !empty($payment['reference_no'])) {
                        $trxId = strtoupper(trim((string) ($payment['reference_no'] ?? '')));
                        $mfsTx = \App\Models\MfsTransaction::where('trx_id', $trxId)
                            ->where('tenant_id', $tenantId)
                            ->where('status', 'unclaimed')
                            ->lockForUpdate()
                            ->first();

                        if (!$mfsTx) {
                            throw ValidationException::withMessages([
                                'payment' => ["MFS Transaction ID '{$trxId}' does not exist, is already claimed, or belongs to another tenant."]
                            ]);
                        }

                        if (abs((float) $mfsTx->amount - (float) $payment['amount']) > 0.01) {
                            throw ValidationException::withMessages([
                                'payment' => ["MFS Transaction ID '{$trxId}' amount (৳{$mfsTx->amount}) does not match payment amount (৳{$payment['amount']})."]
                            ]);
                        }

                        $mfsTx->update(['status' => 'claimed', 'order_id' => $order->id]);
                    }
                }
            } else {
                OrderPayment::create([
                    'tenant_id' => $tenantId,
                    'order_id' => $order->id,
                    'payment_method' => $validated['payment_method'],
                    'amount' => $paidAmount,
                ]);

                if (in_array(strtolower($validated['payment_method']), ['bkash', 'nagad', 'rocket', 'upay'])) {
                    $trxId = strtoupper(trim((string) ($request->input('reference_no') ?? $request->input('transaction_id') ?? '')));
                    $mfsTx = \App\Models\MfsTransaction::where('trx_id', $trxId)
                        ->where('tenant_id', $tenantId)
                        ->where('status', 'unclaimed')
                        ->lockForUpdate()
                        ->first();

                    if (!$mfsTx) {
                        throw ValidationException::withMessages([
                            'payment' => ["MFS Transaction ID '{$trxId}' does not exist, is already claimed, or belongs to another tenant."]
                        ]);
                    }

                    if (abs((float) $mfsTx->amount - $paidAmount) > 0.01) {
                        throw ValidationException::withMessages([
                            'payment' => ["MFS Transaction ID '{$trxId}' amount (৳{$mfsTx->amount}) does not match paid amount (৳{$paidAmount})."]
                        ]);
                    }

                    $mfsTx->update(['status' => 'claimed', 'order_id' => $order->id]);
                }
            }

            // Update Customer Ledger & Loyalty Points
            if ($order->customer_id) {
                $customer = Customer::find($order->customer_id);
                if ($customer) {
                    if ($paymentStatus !== 'paid') {
                        $dueAmount = max(0, $grandTotal - $paidAmount);
                        if ($dueAmount > 0) {
                            $creditLimit = (float) ($customer->credit_limit ?? 50000.00);
                            if ($creditLimit > 0 && ((float) $customer->due_balance + $dueAmount) > $creditLimit) {
                                throw ValidationException::withMessages([
                                    'credit' => ["Customer credit limit of ৳" . number_format($creditLimit, 2) . " exceeded!"]
                                ]);
                            }
                            $customer->increment('due_balance', $dueAmount);
                        }
                    }
                    $earnedPoints = floor($grandTotal / 100);
                    if ($earnedPoints > 0) {
                        $customer->increment('points', $earnedPoints);
                    }
                }
            }

            $order->load(['items', 'customer', 'store', 'payments', 'user']);

            AuditLogger::log(
                'pos_checkout',
                "Processed sale invoice {$order->invoice_no} for total ৳{$order->grand_total}",
                [
                    'invoice_no' => $order->invoice_no,
                    'grand_total' => $order->grand_total,
                    'payment_method' => $order->payment_method,
                    'payment_status' => $order->payment_status,
                ],
                $store->id
            );

            // Record Daily Sales Summary (Executed at final line of transaction for shortest lock holding time)
            $paymentsMap = [];
            foreach ($order->payments as $pmt) {
                $paymentsMap[$pmt->payment_method] = ($paymentsMap[$pmt->payment_method] ?? 0) + (float) $pmt->amount;
            }
            \App\Models\DailySalesSummary::recordSale(
                $tenantId,
                $store->id,
                now()->toDateString(),
                (float) $calculatedSubtotal,
                (float) ($totalInvoiceVatPaisa / 100),
                (float) $grandTotal,
                (float) $calculatedCogs,
                $paymentsMap
            );

            if ($request->wantsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
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
        }, 5);
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

        if (!empty($user->pos_pin) && Hash::check($pin, $user->pos_pin)) {
            return response()->json(['success' => true]);
        }

        return response()->json(['success' => false, 'message' => 'Invalid Cashier PIN.'], 403);
    }

    public function downloadInvoicePdf($id)
    {
        $tenantId = $this->getTenantId();
        $order = Order::where('tenant_id', $tenantId)
            ->with(['items.product', 'customer', 'store', 'user', 'payments'])
            ->where('id', $id)
            ->firstOrFail();

        return view('pdf.invoice', ['order' => $order]);
    }

    public function searchProducts(Request $request)
    {
        $tenantId = $this->getTenantId();
        $storeId = (int) $request->input('store_id', 1);
        $q = trim((string) $request->input('q', ''));

        if (empty($q)) {
            return response()->json([]);
        }

        $products = Product::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->where(function($query) use ($q) {
                $query->where('name', 'LIKE', "%{$q}%")
                      ->orWhere('sku', 'LIKE', "%{$q}%")
                      ->orWhere('barcode', 'LIKE', "%{$q}%");
            })
            ->with(['category', 'unit', 'stocks' => function ($sq) use ($storeId) {
                $sq->where('store_id', $storeId);
            }])
            ->limit(30)
            ->get()
            ->map(function ($p) {
                $stock = $p->stocks ? $p->stocks->first() : null;
                $p->current_stock = $stock ? (float) $stock->quantity : 0;
                return $p;
            });

        return response()->json($products);
    }

    public function searchCustomers(Request $request)
    {
        $tenantId = $this->getTenantId();
        $q = trim((string) $request->input('q', ''));

        if (empty($q)) {
            return response()->json([]);
        }

        $customers = Customer::where('tenant_id', $tenantId)
            ->where(function($query) use ($q) {
                $query->where('name', 'LIKE', "%{$q}%")
                      ->orWhere('phone', 'LIKE', "%{$q}%")
                      ->orWhere('email', 'LIKE', "%{$q}%");
            })
            ->orderBy('name')
            ->limit(30)
            ->get();

        return response()->json($customers);
    }

    public function downloadMushak63($id)
    {
        $tenantId = $this->getTenantId();
        $order = Order::where('tenant_id', $tenantId)
            ->with(['items.product', 'customer', 'store', 'user', 'payments'])
            ->where('id', $id)
            ->firstOrFail();

        return view('pdf.mushak63', ['order' => $order]);
    }

    public function exportSalesCsv(Request $request)
    {
        $tenantId = $this->getTenantId();

        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        if (!$startDate) {
            $startDate = now()->subDays(30)->startOfDay()->toDateTimeString();
        } else {
            $startDate = \Carbon\Carbon::parse($startDate)->startOfDay()->toDateTimeString();
        }

        if (!$endDate) {
            $endDate = now()->endOfDay()->toDateTimeString();
        } else {
            $endDate = \Carbon\Carbon::parse($endDate)->endOfDay()->toDateTimeString();
        }

        $diffInDays = \Carbon\Carbon::parse($startDate)->diffInDays(\Carbon\Carbon::parse($endDate));
        if ($diffInDays > 366) {
            $startDate = \Carbon\Carbon::parse($endDate)->subDays(366)->startOfDay()->toDateTimeString();
        }

        $totalMatchingRows = DB::table('orders')
            ->where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();

        $forceStream = $request->boolean('force_stream') || $request->input('stream') === '1' || $request->boolean('stream');

        // Large exports (> 20,000 rows) run as queued background job unless force_stream is true
        if ($totalMatchingRows > 20000 && !$forceStream) {
            $exportId = uniqid('exp_');
            cache()->put("export_status_{$exportId}", [
                'status' => 'queued',
                'matching_rows' => $totalMatchingRows,
                'created_at' => now()->toDateTimeString(),
            ], 86400);

            \App\Jobs\ExportSalesCsvJob::dispatch($tenantId, $startDate, $endDate, $exportId);

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'queued' => true,
                    'export_id' => $exportId,
                    'matching_rows' => $totalMatchingRows,
                    'message' => "Export containing {$totalMatchingRows} rows dispatched to background queue.",
                    'status_url' => url("/reports/sales/export-status/{$exportId}"),
                ], 202);
            }

            return redirect()->back()->with('success', "Large export ({$totalMatchingRows} rows) queued! File will be generated in background.");
        }

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="sales_report_' . date('Ymd_His') . '.csv"',
        ];

        $callback = function () use ($tenantId, $startDate, $endDate) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['Invoice No', 'Date', 'Store', 'Cashier', 'Customer', 'Subtotal', 'Discount', 'VAT (Tax)', 'Grand Total', 'Payment Method', 'Payment Status']);

            $cursor = DB::table('orders')
                ->where('orders.tenant_id', $tenantId)
                ->whereBetween('orders.created_at', [$startDate, $endDate])
                ->leftJoin('stores', 'orders.store_id', '=', 'stores.id')
                ->leftJoin('users', 'orders.user_id', '=', 'users.id')
                ->leftJoin('customers', 'orders.customer_id', '=', 'customers.id')
                ->select([
                    'orders.id',
                    'orders.invoice_no',
                    'orders.created_at',
                    'stores.name as store_name',
                    'users.name as cashier_name',
                    'customers.name as customer_name',
                    'orders.subtotal',
                    'orders.discount_amount',
                    'orders.tax_amount',
                    'orders.grand_total',
                    'orders.payment_method',
                    'orders.payment_status',
                ])
                ->orderBy('orders.id', 'asc')
                ->cursor();

            foreach ($cursor as $order) {
                fputcsv($file, [
                    $order->invoice_no,
                    $order->created_at,
                    $order->store_name ?? 'N/A',
                    $order->cashier_name ?? 'N/A',
                    $order->customer_name ?? 'Walk-in Customer',
                    $order->subtotal,
                    $order->discount_amount,
                    $order->tax_amount,
                    $order->grand_total,
                    $order->payment_method,
                    $order->payment_status,
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function checkExportStatus($exportId)
    {
        $status = cache()->get("export_status_{$exportId}");
        if (!$status) {
            return response()->json(['success' => false, 'message' => 'Export task not found.'], 404);
        }
        return response()->json(['success' => true, 'export' => $status]);
    }
}
