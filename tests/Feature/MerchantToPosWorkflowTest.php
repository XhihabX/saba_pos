<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ParkedOrder;
use App\Models\Product;
use App\Models\RegisterShift;
use App\Models\Stock;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MerchantToPosWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_complete_merchant_to_pos_counter_end_to_end_workflow(): void
    {


        // =================================================================
        // STEP 1: Merchant Self-Service SaaS Registration
        // =================================================================
        $registrationPayload = [
            'business_name' => 'Apex Tech Superstore',
            'owner_name' => 'Jahidul Islam',
            'email' => 'jahidul@apextech.com',
            'phone' => '01799887766',
            'password' => 'Secret1234!',
            'password_confirmation' => 'Secret1234!',
            'plan_name' => 'Starter POS',
            'payment_method' => 'bkash',
            'sender_number' => '01712345678',
            'transaction_id' => 'TRX9988776655',
        ];

        $regResponse = $this->post('/register', $registrationPayload);
        $regResponse->assertStatus(302);

        // Assert Tenant created in database
        $tenant = Tenant::where('email', 'jahidul@apextech.com')->first();
        $this->assertNotNull($tenant, 'Tenant record must be created upon merchant registration');
        $this->assertEquals('Apex Tech Superstore', $tenant->name);

        // Assert Default Store Branch created
        $store = Store::where('tenant_id', $tenant->id)->first();
        $this->assertNotNull($store, 'Default store outlet branch must be auto-provisioned');

        // Assert Merchant CEO User Account created
        $merchantUser = User::where('email', 'jahidul@apextech.com')->first();
        $this->assertNotNull($merchantUser);
        $this->assertEquals('merchant', $merchantUser->role);

        // =================================================================
        // STEP 2: Super Admin Merchant Approval Queue
        // =================================================================
        $superAdmin = User::create([
            'tenant_id' => null,
            'name' => 'Super Admin',
            'email' => 'admin@iotpos.com',
            'password' => Hash::make('password'),
            'role' => 'super_admin',
        ]);

        $this->actingAs($superAdmin);
        
        $approveResponse = $this->post("/super-admin/tenants/{$tenant->id}/approve");
        $approveResponse->assertStatus(302);

        $tenant->refresh();
        $this->assertEquals('active', $tenant->subscription_status, 'Tenant subscription must be activated by Super Admin');

        // =================================================================
        // STEP 3: Merchant HQ Catalog & Staff Setup
        // =================================================================
        $this->actingAs($merchantUser);

        // Create Category
        $category = Category::create([
            'tenant_id' => $tenant->id,
            'name' => 'Computers & Accessories',
            'slug' => 'computers-accessories',
            'is_active' => true,
        ]);

        // Create Product with SKU, Barcode, Selling Price & Initial Stock
        $product = Product::create([
            'tenant_id' => $tenant->id,
            'category_id' => $category->id,
            'name' => 'Logitech Wireless Laser Mouse',
            'sku' => 'LOG-WLS-M185',
            'barcode' => '890100998877',
            'cost_price' => 650.00,
            'selling_price' => 1250.00,
            'is_active' => true,
        ]);

        // Provision Stock in Store Branch
        Stock::create([
            'tenant_id' => $tenant->id,
            'store_id' => $store->id,
            'product_id' => $product->id,
            'quantity' => 100,
            'reorder_level' => 10,
        ]);

        // Create Customer Profile
        $customer = Customer::create([
            'tenant_id' => $tenant->id,
            'name' => 'Rafiqul Islam',
            'phone' => '01811223344',
            'email' => 'rafiq@example.com',
            'due_balance' => 0.00,
        ]);

        // Provision Cashier Staff User Account
        $cashierUser = User::create([
            'tenant_id' => $tenant->id,
            'store_id' => $store->id,
            'name' => 'Tanvir (Counter 01)',
            'email' => 'cashier.apex@iotpos.com',
            'password' => Hash::make('password'),
            'pos_pin' => '1234',
            'role' => 'cashier',
        ]);

        // =================================================================
        // STEP 4: Cashier Terminal Shift Opening
        // =================================================================
        $this->actingAs($cashierUser);

        $shiftOpenResponse = $this->postJson('/pos/shift/open', [
            'store_id' => $store->id,
            'opening_cash' => 5000.00,
        ]);

        $shiftOpenResponse->assertStatus(200);
        $shiftOpenResponse->assertJson(['success' => true]);

        $activeShift = RegisterShift::where('user_id', $cashierUser->id)
            ->where('status', 'open')
            ->first();
        $this->assertNotNull($activeShift, 'Cashier shift must be open');
        $this->assertEquals(5000.00, (float) $activeShift->opening_cash);

        $cashierUser->update(['pos_pin' => Hash::make('1234')]);

        // =================================================================
        // STEP 5: POS Counter Workstation Sales Checkout
        // =================================================================
        $checkoutPayload = [
            'store_id' => $store->id,
            'customer_id' => $customer->id,
            'items' => [
                [
                    'product_id' => $product->id,
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'unit_price' => 1250.00,
                    'quantity' => 2,
                    'discount' => 0,
                ]
            ],
            'subtotal' => 2500.00,
            'discount_amount' => 100.00,
            'supervisor_pin' => '1234',
            'tax_amount' => 360.00,
            'grand_total' => 2760.00,
            'paid_amount' => 3000.00,
            'change_return' => 240.00,
            'payment_method' => 'cash',
            'payments' => [
                ['method' => 'cash', 'amount' => 3000.00]
            ],
        ];

        $checkoutResponse = $this->from('/pos')->post('/pos/checkout', $checkoutPayload);
        $checkoutResponse->assertStatus(302);

        // Verify Order Record created
        $order = Order::where('tenant_id', $tenant->id)->first();
        $this->assertNotNull($order, 'Order record must be created in database');
        $this->assertEquals(2760.00, (float) $order->grand_total);
        $this->assertEquals('paid', $order->payment_status);

        // Verify Inventory Stock Deduction
        $updatedStock = Stock::where('store_id', $store->id)->where('product_id', $product->id)->first();
        $this->assertEquals(98, (int) $updatedStock->quantity, 'Stock must be atomically deducted from 100 to 98');

        // =================================================================
        // STEP 6: Parked Sales Order Hold & Discard Lifecycle
        // =================================================================
        $parkResponse = $this->from('/pos')->post('/pos/park', [
            'store_id' => $store->id,
            'customer_name' => 'Walk-in Customer',
            'cart_data' => [
                ['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 1250.00]
            ],
        ]);
        $parkResponse->assertStatus(302);

        $parkedOrder = ParkedOrder::where('store_id', $store->id)->first();
        $this->assertNotNull($parkedOrder, 'Parked order must be saved');

        $discardResponse = $this->from('/pos')->delete("/pos/parked/{$parkedOrder->id}");
        $discardResponse->assertStatus(302);
        $this->assertNull(ParkedOrder::find($parkedOrder->id), 'Parked order must be discarded');

        // =================================================================
        // STEP 7: Cashier Register Shift Closing & Physical Cash Reconciliation
        // =================================================================
        $shiftCloseResponse = $this->postJson('/pos/shift/close', [
            'shift_id' => $activeShift->id,
            'closing_cash_counted' => 7760.00, // 5000 opening + 2760 sales = 7760 expected
        ]);

        $shiftCloseResponse->assertStatus(200);
        $shiftCloseResponse->assertJson(['success' => true]);

        $closedShift = RegisterShift::find($activeShift->id);
        $this->assertEquals('closed', $closedShift->status);
        $this->assertEquals(0.00, (float) $closedShift->cash_difference, 'Drawer cash reconciliation should have 0 variance');
    }

    public function test_pos_checkout_requires_active_shift(): void
    {
        $tenant = Tenant::create(['name' => 'Shift Test Merchant', 'email' => 'shifttest@iotpos.com', 'code' => 'SHIFTTEST', 'subscription_status' => 'active', 'expires_at' => now()->addYear()]);

        $store = Store::create(['tenant_id' => $tenant->id, 'name' => 'Main Outlet', 'code' => 'STORE001']);
        $user = User::create([
            'tenant_id' => $tenant->id,
            'store_id' => $store->id,
            'name' => 'Cashier Shift Guard',
            'email' => 'cashier.shift@iotpos.com',
            'password' => Hash::make('password'),
            'role' => 'cashier',
        ]);
        $product = Product::create([
            'tenant_id' => $tenant->id,
            'name' => 'Test Item',
            'sku' => 'TST-001',
            'cost_price' => 50,
            'selling_price' => 100,
        ]);
        Stock::create(['tenant_id' => $tenant->id, 'store_id' => $store->id, 'product_id' => $product->id, 'quantity' => 10]);

        $this->actingAs($user);

        // Checkout without open shift should fail validation
        $response = $this->postJson('/pos/checkout', [
            'store_id' => $store->id,
            'items' => [['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 100]],
            'paid_amount' => 100,
            'payment_method' => 'cash',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['shift']);
    }
}
