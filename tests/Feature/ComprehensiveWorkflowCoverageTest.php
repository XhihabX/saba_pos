<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductReturn;
use App\Models\RegisterShift;
use App\Models\Stock;
use App\Models\StockAdjustment;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class ComprehensiveWorkflowCoverageTest extends TestCase
{
    use RefreshDatabase;

    protected $merchant;
    protected $tenant;
    protected $storeA;
    protected $storeB;
    protected $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Workflow Coverage Tenant Group',
            'code' => 'TEN-COVERAGE-01',
            'email' => 'coverage@iotpos.com',
            'status' => 'active',
            'subscription_status' => 'active',
            'expires_at' => now()->addYear(),
        ]);

        $this->merchant = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Workflow Admin',
            'email' => 'workflow.admin@iotpos.com',
            'password' => Hash::make('password123'),
            'pos_pin' => Hash::make('1234'),
            'role' => 'merchant',
            'is_approved' => true,
        ]);

        $this->storeA = Store::create([
            'tenant_id' => $this->tenant->id,
            'code' => 'STORE-A',
            'name' => 'Main Outlet Store A',
            'bin_number' => '1122334455',
            'is_vat_registered' => true,
            'allow_negative_stock' => false,
            'default_tax_rate' => 15.0,
        ]);

        $this->storeB = Store::create([
            'tenant_id' => $this->tenant->id,
            'code' => 'STORE-B',
            'name' => 'Branch Outlet Store B',
            'bin_number' => '5544332211',
            'is_vat_registered' => true,
            'allow_negative_stock' => false,
            'default_tax_rate' => 15.0,
        ]);

        $this->product = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Enterprise Coverage Item',
            'sku' => 'COVERAGE-SKU-001',
            'purchase_cost' => 100.00,
            'selling_price' => 200.00,
            'vat_rate' => 15.0,
            'is_active' => true,
        ]);

        Stock::create([
            'tenant_id' => $this->tenant->id,
            'store_id' => $this->storeA->id,
            'product_id' => $this->product->id,
            'quantity' => 100,
        ]);

        Stock::create([
            'tenant_id' => $this->tenant->id,
            'store_id' => $this->storeB->id,
            'product_id' => $this->product->id,
            'quantity' => 20,
        ]);
    }

    // =========================================================================
    // 1. CHECKOUT PATH TESTS
    // =========================================================================

    public function test_checkout_split_payment_discount_and_supervisor_pin_validation(): void
    {
        // Open active register shift
        RegisterShift::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->merchant->id,
            'store_id' => $this->storeA->id,
            'opening_cash' => 500.00,
            'status' => 'open',
            'opened_at' => now(),
        ]);

        // Case A: Cart discount without supervisor PIN fails validation
        $badPayload = [
            'client_uuid' => (string) \Illuminate\Support\Str::uuid(),
            'store_id' => $this->storeA->id,
            'items' => [['product_id' => $this->product->id, 'quantity' => 2]],
            'discount_amount' => 50.00,
            'paid_amount' => 410.00,
            'payment_method' => 'cash',
        ];

        $resBad = $this->actingAs($this->merchant)->postJson('/pos/checkout', $badPayload);
        $resBad->assertStatus(422)->assertJsonValidationErrors(['discount']);

        // Case B: Split payment checkout with valid supervisor PIN succeeds
        $goodPayload = [
            'store_id' => $this->storeA->id,
            'items' => [['product_id' => $this->product->id, 'quantity' => 2]],
            'discount_amount' => 50.00,
            'supervisor_pin' => '1234',
            'paid_amount' => 402.50, // Subtotal (400 - 50 = 350) + 15% VAT (52.50)
            'payment_method' => 'split',
            'payments' => [
                ['method' => 'cash', 'amount' => 200.00],
                ['method' => 'card', 'amount' => 202.50],
            ],
            'client_uuid' => 'UUID-SPLIT-' . Str::uuid(),
        ];

        $resGood = $this->actingAs($this->merchant)->postJson('/pos/checkout', $goodPayload);
        $resGood->assertStatus(200);

        // Verify stock decremented from 100 to 98
        $this->assertDatabaseHas('stocks', [
            'store_id' => $this->storeA->id,
            'product_id' => $this->product->id,
            'quantity' => 98,
        ]);
    }

    // =========================================================================
    // 2. PRODUCT RETURN PATH TESTS
    // =========================================================================

    public function test_product_return_workflow_and_stock_restoration(): void
    {
        $order = Order::create([
            'tenant_id' => $this->tenant->id,
            'store_id' => $this->storeA->id,
            'user_id' => $this->merchant->id,
            'invoice_no' => 'INV-RET-1001',
            'subtotal' => 400.00,
            'grand_total' => 460.00,
            'paid_amount' => 460.00,
            'payment_status' => 'paid',
            'payment_method' => 'cash',
        ]);

        OrderItem::create([
            'tenant_id' => $this->tenant->id,
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'product_name' => $this->product->name,
            'quantity' => 2,
            'unit_price' => 200.00,
            'cost_price' => 100.00,
            'total' => 400.00,
        ]);

        $returnPayload = [
            'invoice_no' => $order->invoice_no,
            'product_id' => $this->product->id,
            'quantity' => 1,
            'refund_amount' => 200.00,
            'reason' => 'Defective packaging',
        ];

        $response = $this->actingAs($this->merchant)->post('/manager/returns', $returnPayload);
        $response->assertStatus(302);

        // Assert ProductReturn recorded
        $this->assertDatabaseHas('product_returns', [
            'tenant_id' => $this->tenant->id,
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'quantity' => 1,
            'refund_amount' => 200.00,
        ]);

        // Assert stock restored from 100 to 101
        $this->assertDatabaseHas('stocks', [
            'store_id' => $this->storeA->id,
            'product_id' => $this->product->id,
            'quantity' => 101,
        ]);
    }

    // =========================================================================
    // 3. REGISTER SHIFT AUDIT & RECONCILIATION PATH TESTS
    // =========================================================================

    public function test_register_shift_open_close_and_reconciliation_workflow(): void
    {
        // 1. Open shift
        $openRes = $this->actingAs($this->merchant)->post('/pos/shift/open', [
            'store_id' => $this->storeA->id,
            'opening_cash' => 1000.00,
        ]);
        $openRes->assertSuccessful();


        $shift = RegisterShift::where('user_id', $this->merchant->id)->where('status', 'open')->first();
        $this->assertNotNull($shift);
        $this->assertEquals(1000.00, (float) $shift->opening_cash);

        // 2. Process cash sale during shift
        Order::create([
            'tenant_id' => $this->tenant->id,
            'store_id' => $this->storeA->id,
            'user_id' => $this->merchant->id,
            'invoice_no' => 'INV-SHIFT-101',
            'subtotal' => 200.00,
            'grand_total' => 230.00,
            'paid_amount' => 230.00,
            'payment_status' => 'paid',
            'payment_method' => 'cash',
        ]);

        // 3. Close shift with physical cash count
        $closeRes = $this->actingAs($this->merchant)->post('/pos/shift/close', [
            'shift_id' => $shift->id,
            'closing_cash_counted' => 1000.00,
            'notes' => 'Perfect cash drawer balance',
        ]);
        $closeRes->assertStatus(302);

        $closedShift = $shift->fresh();
        $this->assertEquals('closed', $closedShift->status);

        // 4. Z-Report view access
        $zRes = $this->actingAs($this->merchant)->get("/manager/shifts/{$shift->id}/z-report");
        $zRes->assertStatus(200);
    }

    // =========================================================================
    // 4. STOCK TRANSFER PATH TESTS
    // =========================================================================

    public function test_inter_store_stock_transfer_workflow(): void
    {
        $transferPayload = [
            'from_store_id' => $this->storeA->id,
            'to_store_id' => $this->storeB->id,
            'notes' => 'Replenish Branch Store B inventory',
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 10,
                ]
            ]
        ];

        $response = $this->actingAs($this->merchant)->post('/manager/transfers', $transferPayload);
        $response->assertStatus(302);

        // Assert StockTransfer record created
        $this->assertDatabaseHas('stock_transfers', [
            'tenant_id' => $this->tenant->id,
            'from_store_id' => $this->storeA->id,
            'to_store_id' => $this->storeB->id,
            'status' => 'completed',
        ]);

        // Assert stock transferred: Store A reduced by 10 (100 -> 90), Store B increased by 10 (20 -> 30)
        $this->assertDatabaseHas('stocks', [
            'store_id' => $this->storeA->id,
            'product_id' => $this->product->id,
            'quantity' => 90,
        ]);

        $this->assertDatabaseHas('stocks', [
            'store_id' => $this->storeB->id,
            'product_id' => $this->product->id,
            'quantity' => 30,
        ]);
    }

    // =========================================================================
    // 5. STOCK ADJUSTMENT PATH TESTS
    // =========================================================================

    public function test_manual_stock_adjustment_increase_and_decrease_workflow(): void
    {
        // 1. Stock Adjustment Addition (+15)
        $addPayload = [
            'store_id' => $this->storeA->id,
            'product_id' => $this->product->id,
            'type' => 'audit_addition',
            'quantity' => 15,
            'notes' => 'Supplier bonus stock received',
        ];

        $resAdd = $this->actingAs($this->merchant)->post('/inventory/adjustments', $addPayload);
        $resAdd->assertStatus(302);

        $this->assertDatabaseHas('stocks', [
            'store_id' => $this->storeA->id,
            'product_id' => $this->product->id,
            'quantity' => 115,
        ]);

        // 2. Stock Adjustment Subtraction (-5)
        $subPayload = [
            'store_id' => $this->storeA->id,
            'product_id' => $this->product->id,
            'type' => 'audit_deduction',
            'quantity' => 5,
            'notes' => 'Damaged during shelf handling',
        ];

        $resSub = $this->actingAs($this->merchant)->post('/inventory/adjustments', $subPayload);
        $resSub->assertStatus(302);

        $this->assertDatabaseHas('stocks', [
            'store_id' => $this->storeA->id,
            'product_id' => $this->product->id,
            'quantity' => 110,
        ]);
    }
}
