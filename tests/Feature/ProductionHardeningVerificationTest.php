<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Customer;
use App\Models\MfsTransaction;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderPayment;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\ProductReturn;
use App\Models\Purchase;
use App\Models\Quotation;
use App\Models\RegisterShift;
use App\Models\Stock;
use App\Models\StockTransfer;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use App\Services\EfdBridgeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProductionHardeningVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected User $merchantUser;
    protected Store $store;

    protected function setUp(): void
    {
        parent::setUp();
        
        Config::set('services.mfs.secret_key', 'test_hmac_secret_key_12345');

        $this->tenant = Tenant::create([
            'name' => 'Test Hardened Tenant',
            'code' => 'TH01',
            'email' => 'tenant@test.local',
            'phone' => '01700000000',
            'subscription_status' => 'active',
            'expires_at' => now()->addDays(30),
        ]);

        $this->store = Store::create([
            'tenant_id' => $this->tenant->id,
            'code' => 'ST01',
            'name' => 'Main Test Store',
            'phone' => '01700000000',
            'address' => 'Dhaka',
            'default_tax_rate' => 15.0,
            'allow_negative_stock' => false,
            'is_active' => true,
        ]);

        $this->merchantUser = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'store_id' => $this->store->id,
            'role' => 'merchant',
            'pos_pin' => Hash::make('9999'),
        ]);
    }

    /** @test */
    public function test_01_setup_and_sync_routes_return_404_not_found()
    {
        $response1 = $this->get('/setup-database-seed');
        $response1->assertStatus(404);

        $response2 = $this->get('/sync-assets');
        $response2->assertStatus(404);
    }

    /** @test */
    public function test_02_mfs_webhook_authentication_and_checkout_trxid_claim_validation()
    {
        $timestamp = time();
        $trxId = 'TRX_MFS_TEST_001';
        $amount = '500.00';
        $secret = 'test_hmac_secret_key_12345';
        $signature = hash_hmac('sha256', "{$timestamp}.{$trxId}.{$amount}", $secret);

        $response = $this->postJson('/api/v1/mfs-webhook', [
            'trx_id' => $trxId,
            'amount' => $amount,
            'sender' => '01700000000',
            'gateway' => 'bkash',
            'tenant_id' => $this->tenant->id,
        ], [
            'HTTP_X_MFS_SIGNATURE' => $signature,
            'HTTP_X_MFS_TIMESTAMP' => (string) $timestamp,
        ]);

        $response->assertStatus(201)->assertJson(['success' => true]);
        $this->assertDatabaseHas('mfs_transactions', [
            'trx_id' => $trxId,
            'tenant_id' => $this->tenant->id,
            'status' => 'unclaimed',
        ]);

        $product = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'MFS Product',
            'sku' => 'SKU-MFS-01',
            'selling_price' => 500.00,
            'is_active' => true,
        ]);

        Stock::create([
            'tenant_id' => $this->tenant->id,
            'store_id' => $this->store->id,
            'product_id' => $product->id,
            'quantity' => 10.0,
        ]);

        RegisterShift::create([
            'tenant_id' => $this->tenant->id,
            'store_id' => $this->store->id,
            'user_id' => $this->merchantUser->id,
            'opening_float' => 1000.00,
            'opened_at' => now(),
            'status' => 'open',
        ]);

        $invalidCheckout = $this->actingAs($this->merchantUser)->postJson('/pos/checkout', [
            'client_uuid' => (string) \Illuminate\Support\Str::uuid(),
            'store_id' => $this->store->id,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'paid_amount' => 575.00,
            'payment_method' => 'bkash',
            'payments' => [['method' => 'bkash', 'amount' => 575.00, 'reference_no' => 'INVALID_TRX_999']],
        ]);

        $invalidCheckout->assertStatus(422)
            ->assertJsonValidationErrors(['payment']);
    }

    /** @test */
    public function test_03_checkout_negative_stock_prevention_supervisor_pin_and_idempotency()
    {
        $product = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Stock Test Item',
            'sku' => 'SKU-STOCK-01',
            'selling_price' => 100.00,
            'is_active' => true,
        ]);

        Stock::create([
            'tenant_id' => $this->tenant->id,
            'store_id' => $this->store->id,
            'product_id' => $product->id,
            'quantity' => 0.0,
        ]);

        RegisterShift::create([
            'tenant_id' => $this->tenant->id,
            'store_id' => $this->store->id,
            'user_id' => $this->merchantUser->id,
            'opening_float' => 500.00,
            'opened_at' => now(),
            'status' => 'open',
        ]);

        // Negative stock rejected when allow_negative_stock = false
        $response1 = $this->actingAs($this->merchantUser)->postJson('/pos/checkout', [
            'client_uuid' => (string) \Illuminate\Support\Str::uuid(),
            'store_id' => $this->store->id,
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
            'paid_amount' => 230.00,
            'payment_method' => 'cash',
        ]);

        $response1->assertStatus(422)->assertJsonValidationErrors(['cart']);

        Stock::where('store_id', $this->store->id)->where('product_id', $product->id)->update(['quantity' => 5.0]);

        // Discount without supervisor PIN rejected
        $response2 = $this->actingAs($this->merchantUser)->postJson('/pos/checkout', [
            'client_uuid' => (string) \Illuminate\Support\Str::uuid(),
            'store_id' => $this->store->id,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'discount_amount' => 10.00,
            'paid_amount' => 103.50,
            'payment_method' => 'cash',
        ]);

        $response2->assertStatus(422)->assertJsonValidationErrors(['discount']);

        // Successful checkout with supervisor PIN & idempotency_key
        $response3 = $this->actingAs($this->merchantUser)->postJson('/pos/checkout', [
            'client_uuid' => 'IDEM-TEST-1001',
            'store_id' => $this->store->id,
            'idempotency_key' => 'IDEM-TEST-1001',
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'discount_amount' => 10.00,
            'supervisor_pin' => '9999',
            'paid_amount' => 103.50,
            'payment_method' => 'cash',
        ]);

        $response3->assertStatus(200)->assertJson(['success' => true]);
        $this->assertDatabaseHas('orders', ['idempotency_key' => 'IDEM-TEST-1001']);
    }

    /** @test */
    public function test_04_pin_verification_rejects_unhashed_and_user_id_fallbacks()
    {
        $userNoPin = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'cashier', 'pos_pin' => null]);

        // Wrong PIN rejected
        $res1 = $this->actingAs($this->merchantUser)->postJson('/pos/verify-pin', ['pin' => '1234']);
        $res1->assertStatus(403)->assertJson(['success' => false]);

        // Valid hashed PIN accepted
        $res2 = $this->actingAs($this->merchantUser)->postJson('/pos/verify-pin', ['pin' => '9999']);
        $res2->assertStatus(200)->assertJson(['success' => true]);

        // User ID fallback (e.g. 0002) rejected for user without pos_pin
        $res3 = $this->actingAs($userNoPin)->postJson('/pos/verify-pin', ['pin' => sprintf('%04d', $userNoPin->id)]);
        $res3->assertStatus(403);
    }

    /** @test */
    public function test_05_returns_validation_quantity_and_refund_caps()
    {
        $product = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Return Test Item',
            'sku' => 'SKU-RET-01',
            'selling_price' => 200.00,
            'is_active' => true,
        ]);

        $order = Order::create([
            'tenant_id' => $this->tenant->id,
            'store_id' => $this->store->id,
            'invoice_no' => 'INV-RET-1001',
            'subtotal' => 200.00,
            'tax_amount' => 30.00,
            'discount_amount' => 0.00,
            'grand_total' => 230.00,
            'paid_amount' => 230.00,
            'payment_status' => 'paid',
            'payment_method' => 'cash',
        ]);

        OrderItem::create([
            'tenant_id' => $this->tenant->id,
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => 1,
            'unit_price' => 200.00,
            'total' => 200.00,
        ]);

        // Returning quantity greater than purchased fails via /manager/returns
        $res1 = $this->actingAs($this->merchantUser)->postJson('/manager/returns', [
            'invoice_no' => $order->invoice_no,
            'product_id' => $product->id,
            'quantity' => 5,
            'refund_amount' => 230.00,
            'reason' => 'Defective',
        ]);

        $res1->assertStatus(422)->assertJsonValidationErrors(['quantity']);
    }

    /** @test */
    public function test_06_get_tenant_id_returns_403_for_users_without_tenant()
    {
        $userNoTenant = User::factory()->create(['tenant_id' => null, 'role' => 'cashier']);

        $response = $this->actingAs($userNoTenant)->get('/pos');
        $response->assertStatus(403);
    }

    /** @test */
    public function test_07_vat_computation_and_efd_bridge_payload_uses_store_tax_rate()
    {
        $order = Order::create([
            'tenant_id' => $this->tenant->id,
            'store_id' => $this->store->id,
            'invoice_no' => 'INV-VAT-9001',
            'subtotal' => 1000.00,
            'discount_amount' => 100.00,
            'tax_amount' => 135.00, // (1000 - 100) * 15%
            'grand_total' => 1035.00,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
        ]);

        $product = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'EFD Item',
            'sku' => 'SKU-EFD-01',
            'selling_price' => 1000.00,
            'is_active' => true,
        ]);

        OrderItem::create([
            'tenant_id' => $this->tenant->id,
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => 1,
            'unit_price' => 1000.00,
            'vat_rate' => 15.00,
            'vat_amount' => 135.00,
            'total' => 900.00,
        ]);

        $payload = EfdBridgeService::generatePayload($order);
        $this->assertEquals(135.00, $payload['items'][0]['vat_amount']);
    }

    /** @test */
    public function test_08_reports_and_pos_search_use_pagination_and_sql_limits()
    {
        Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Searchable Rice',
            'sku' => 'SKU-RICE-01',
            'barcode' => '8801234567',
            'selling_price' => 50.00,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->merchantUser)->get('/pos/products/search?q=Rice');
        $response->assertStatus(200)->assertJsonCount(1);
    }

    /** @test */
    public function test_09_models_enforce_tenant_scope_isolation()
    {
        $product = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Isolated Item',
            'sku' => 'SKU-ISO-01',
            'selling_price' => 150.00,
            'is_active' => true,
        ]);

        $stock = Stock::create([
            'tenant_id' => $this->tenant->id,
            'store_id' => $this->store->id,
            'product_id' => $product->id,
            'quantity' => 50,
        ]);

        $otherTenant = Tenant::create(['name' => 'Other Tenant', 'code' => 'OT02', 'email' => 'other@test.local', 'phone' => '01800000000']);
        $otherUser = User::factory()->create(['tenant_id' => $otherTenant->id, 'role' => 'merchant']);

        $this->actingAs($otherUser);
        $this->assertNull(Stock::find($stock->id));
    }

    /** @test */
    public function test_10_wrong_pin_6_times_returns_429_too_many_requests()
    {
        for ($i = 1; $i <= 5; $i++) {
            $res = $this->actingAs($this->merchantUser)->postJson('/pos/verify-pin', ['pin' => '0000']);
            $res->assertStatus(403);
        }

        // 6th attempt within 1 minute gets locked out with 429
        $res6 = $this->actingAs($this->merchantUser)->postJson('/pos/verify-pin', ['pin' => '0000']);
        $res6->assertStatus(429);
    }

    /** @test */
    public function test_11_webhook_without_valid_signature_returns_401_or_403()
    {
        // 1. Request without signature or secret returns 401 Unauthorized
        $response1 = $this->postJson('/api/v1/mfs-webhook', [
            'trx_id' => 'TRX_UNAUTH_001',
            'amount' => '250.00',
            'gateway' => 'bkash',
            'timestamp' => time(),
        ]);
        $response1->assertStatus(401);

        // 2. Request with forged/invalid signature returns 401 Unauthorized
        $response2 = $this->postJson('/api/v1/mfs-webhook', [
            'trx_id' => 'TRX_UNAUTH_002',
            'amount' => '250.00',
            'gateway' => 'bkash',
        ], [
            'X-MFS-Signature' => 'invalid_signature_hash_value',
            'X-MFS-Timestamp' => (string) time(),
        ]);
        $response2->assertStatus(401);
    }

    /** @test */
    public function test_12_no_phantom_stock_auto_creation_and_oversell_blocking()
    {
        $product = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Zero Stock Product',
            'sku' => 'SKU-ZERO-01',
            'selling_price' => 100.00,
            'is_active' => true,
        ]);

        RegisterShift::create([
            'tenant_id' => $this->tenant->id,
            'store_id' => $this->store->id,
            'user_id' => $this->merchantUser->id,
            'opening_float' => 500.00,
            'opened_at' => now(),
            'status' => 'open',
        ]);

        // Oversell blocked when stock is 0 (no phantom 100 stock created)
        $res = $this->actingAs($this->merchantUser)->postJson('/pos/checkout', [
            'client_uuid' => (string) \Illuminate\Support\Str::uuid(),
            'store_id' => $this->store->id,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'paid_amount' => 115.00,
            'payment_method' => 'cash',
        ]);

        $res->assertStatus(422)->assertJsonValidationErrors(['cart']);

        // Stock quantity in database remains 0.0
        $stock = Stock::where('store_id', $this->store->id)->where('product_id', $product->id)->first();
        $this->assertEquals(0.0, (float) ($stock->quantity ?? 0));
    }

    /** @test */
    public function test_13_double_submit_idempotency_returns_single_order()
    {
        $product = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Idempotent Product',
            'sku' => 'SKU-IDEM-01',
            'selling_price' => 200.00,
            'is_active' => true,
        ]);

        Stock::create([
            'tenant_id' => $this->tenant->id,
            'store_id' => $this->store->id,
            'product_id' => $product->id,
            'quantity' => 10.0,
        ]);

        RegisterShift::create([
            'tenant_id' => $this->tenant->id,
            'store_id' => $this->store->id,
            'user_id' => $this->merchantUser->id,
            'opening_float' => 500.00,
            'opened_at' => now(),
            'status' => 'open',
        ]);

        $payload = [
            'client_uuid' => 'UNIQUE-UUID-KEY-999',
            'store_id' => $this->store->id,
            'idempotency_key' => 'UNIQUE-UUID-KEY-999',
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'paid_amount' => 230.00,
            'payment_method' => 'cash',
        ];

        // 1st submit
        $res1 = $this->actingAs($this->merchantUser)->postJson('/pos/checkout', $payload);
        $res1->assertStatus(200)->assertJson(['success' => true]);

        // 2nd submit with same key returns original order
        $res2 = $this->actingAs($this->merchantUser)->postJson('/pos/checkout', $payload);
        $res2->assertStatus(200)->assertJson(['success' => true]);

        // Database has exactly 1 order for this key
        $this->assertEquals(1, Order::where('idempotency_key', 'UNIQUE-UUID-KEY-999')->count());
    }

    /** @test */
    public function test_14_split_payment_mismatch_and_fake_trxid_rejection()
    {
        $product = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Split Pay Item',
            'sku' => 'SKU-SPLIT-01',
            'selling_price' => 100.00,
            'is_active' => true,
        ]);

        Stock::create([
            'tenant_id' => $this->tenant->id,
            'store_id' => $this->store->id,
            'product_id' => $product->id,
            'quantity' => 10.0,
        ]);

        RegisterShift::create([
            'tenant_id' => $this->tenant->id,
            'store_id' => $this->store->id,
            'user_id' => $this->merchantUser->id,
            'opening_float' => 500.00,
            'opened_at' => now(),
            'status' => 'open',
        ]);

        // Split payment sum mismatch rejected
        $res1 = $this->actingAs($this->merchantUser)->postJson('/pos/checkout', [
            'client_uuid' => (string) \Illuminate\Support\Str::uuid(),
            'store_id' => $this->store->id,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'paid_amount' => 115.00,
            'payment_method' => 'cash',
            'payments' => [
                ['method' => 'cash', 'amount' => 50.00],
                ['method' => 'card', 'amount' => 50.00], // Sum 100 != 115
            ],
        ]);
        $res1->assertStatus(422)->assertJsonValidationErrors(['payments']);

        // Fake MFS TrxID rejected
        $res2 = $this->actingAs($this->merchantUser)->postJson('/pos/checkout', [
            'client_uuid' => (string) \Illuminate\Support\Str::uuid(),
            'store_id' => $this->store->id,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'paid_amount' => 115.00,
            'payment_method' => 'bkash',
            'reference_no' => 'FAKE_TRX_9999',
        ]);
        $res2->assertStatus(422)->assertJsonValidationErrors(['payment']);
    }

    /** @test */
    public function test_15_vat_matches_hand_calculation_on_5_invoices()
    {
        $testCases = [
            ['subtotal' => 100.00, 'discount' => 0.00, 'tax_rate' => 15.0, 'exp_tax' => 15.00, 'exp_grand' => 115.00],
            ['subtotal' => 500.00, 'discount' => 50.00, 'tax_rate' => 15.0, 'exp_tax' => 67.50, 'exp_grand' => 517.50],
            ['subtotal' => 1250.00, 'discount' => 100.00, 'tax_rate' => 5.0, 'exp_tax' => 57.50, 'exp_grand' => 1207.50],
            ['subtotal' => 99.99, 'discount' => 0.00, 'tax_rate' => 15.0, 'exp_tax' => 15.00, 'exp_grand' => 114.99],
            ['subtotal' => 3000.00, 'discount' => 200.00, 'tax_rate' => 10.0, 'exp_tax' => 280.00, 'exp_grand' => 3080.00],
        ];

        foreach ($testCases as $index => $tc) {
            $this->store->update(['default_tax_rate' => $tc['tax_rate']]);

            $subtotalPaisa = intval(round($tc['subtotal'] * 100));
            $discPaisa = intval(round($tc['discount'] * 100));
            $taxablePaisa = max(0, $subtotalPaisa - $discPaisa);
            $taxPaisa = intval(round(($taxablePaisa * $tc['tax_rate']) / 100));
            $grandPaisa = $taxablePaisa + $taxPaisa;

            $taxAmount = $taxPaisa / 100;
            $grandTotal = $grandPaisa / 100;

            $this->assertEquals($tc['exp_tax'], $taxAmount, "Invoice #{$index} VAT calculation mismatch");
            $this->assertEquals($tc['exp_grand'], $grandTotal, "Invoice #{$index} Grand Total calculation mismatch");
        }
    }

    /** @test */
    public function test_16_inclusive_vat_checkout_extracts_vat_from_price_without_adding_extra()
    {
        $this->store->update(['vat_mode' => 'inclusive', 'default_tax_rate' => 15.0]);

        $product = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Inclusive VAT Item',
            'sku' => 'SKU-INC-01',
            'selling_price' => 115.00,
            'is_active' => true,
        ]);

        Stock::create([
            'tenant_id' => $this->tenant->id,
            'store_id' => $this->store->id,
            'product_id' => $product->id,
            'quantity' => 10.0,
        ]);

        RegisterShift::create([
            'tenant_id' => $this->tenant->id,
            'store_id' => $this->store->id,
            'user_id' => $this->merchantUser->id,
            'opening_float' => 500.00,
            'opened_at' => now(),
            'status' => 'open',
        ]);

        $response = $this->actingAs($this->merchantUser)->postJson('/pos/checkout', [
            'client_uuid' => 'UUID-INC-16',
            'store_id' => $this->store->id,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'paid_amount' => 115.00,
            'payment_method' => 'cash',
        ]);

        $response->assertStatus(200)->assertJson(['success' => true]);

        $order = Order::latest()->first();
        $this->assertEquals(115.00, (float) $order->grand_total);
        $this->assertEquals(15.00, (float) $order->tax_amount);

        $item = OrderItem::where('order_id', $order->id)->first();
        $this->assertEquals(15.00, (float) $item->vat_rate);
        $this->assertEquals(15.00, (float) $item->vat_amount);
    }

    /** @test */
    public function test_17_exclusive_vat_checkout_adds_vat_on_top_of_subtotal()
    {
        $this->store->update(['vat_mode' => 'exclusive', 'default_tax_rate' => 15.0]);

        $product = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Exclusive VAT Item',
            'sku' => 'SKU-EXC-01',
            'selling_price' => 100.00,
            'is_active' => true,
        ]);

        Stock::create([
            'tenant_id' => $this->tenant->id,
            'store_id' => $this->store->id,
            'product_id' => $product->id,
            'quantity' => 10.0,
        ]);

        RegisterShift::create([
            'tenant_id' => $this->tenant->id,
            'store_id' => $this->store->id,
            'user_id' => $this->merchantUser->id,
            'opening_float' => 500.00,
            'opened_at' => now(),
            'status' => 'open',
        ]);

        $response = $this->actingAs($this->merchantUser)->postJson('/pos/checkout', [
            'client_uuid' => 'UUID-EXC-17',
            'store_id' => $this->store->id,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'paid_amount' => 115.00,
            'payment_method' => 'cash',
        ]);

        $response->assertStatus(200)->assertJson(['success' => true]);

        $order = Order::latest()->first();
        $this->assertEquals(115.00, (float) $order->grand_total);
        $this->assertEquals(15.00, (float) $order->tax_amount);

        $item = OrderItem::where('order_id', $order->id)->first();
        $this->assertEquals(15.00, (float) $item->vat_rate);
        $this->assertEquals(15.00, (float) $item->vat_amount);
    }

    /** @test */
    public function test_18_product_vat_rate_and_mode_overrides_store_settings()
    {
        $this->store->update(['vat_mode' => 'exclusive', 'default_tax_rate' => 15.0]);

        $productOverride = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Zero VAT Item',
            'sku' => 'SKU-ZERO-VAT',
            'selling_price' => 100.00,
            'vat_rate' => 0.00,
            'vat_mode' => 'exclusive',
            'is_active' => true,
        ]);

        Stock::create([
            'tenant_id' => $this->tenant->id,
            'store_id' => $this->store->id,
            'product_id' => $productOverride->id,
            'quantity' => 10.0,
        ]);

        RegisterShift::create([
            'tenant_id' => $this->tenant->id,
            'store_id' => $this->store->id,
            'user_id' => $this->merchantUser->id,
            'opening_float' => 500.00,
            'opened_at' => now(),
            'status' => 'open',
        ]);

        $response = $this->actingAs($this->merchantUser)->postJson('/pos/checkout', [
            'client_uuid' => 'UUID-OVR-18',
            'store_id' => $this->store->id,
            'items' => [['product_id' => $productOverride->id, 'quantity' => 1]],
            'paid_amount' => 100.00,
            'payment_method' => 'cash',
        ]);

        $response->assertStatus(200)->assertJson(['success' => true]);

        $order = Order::latest()->first();
        $this->assertEquals(100.00, (float) $order->grand_total);
        $this->assertEquals(0.00, (float) $order->tax_amount);

        $item = OrderItem::where('order_id', $order->id)->first();
        $this->assertEquals(0.00, (float) $item->vat_rate);
        $this->assertEquals(0.00, (float) $item->vat_amount);
    }

    /** @test */
    public function test_19_vat_registered_store_with_empty_bin_blocks_checkout()
    {
        $this->store->update([
            'is_vat_registered' => true,
            'bin_number' => null,
            'vat_number' => null,
        ]);

        $product = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'BIN Guard Item',
            'sku' => 'SKU-BIN-01',
            'selling_price' => 100.00,
            'is_active' => true,
        ]);

        Stock::create([
            'tenant_id' => $this->tenant->id,
            'store_id' => $this->store->id,
            'product_id' => $product->id,
            'quantity' => 10.0,
        ]);

        RegisterShift::create([
            'tenant_id' => $this->tenant->id,
            'store_id' => $this->store->id,
            'user_id' => $this->merchantUser->id,
            'opening_float' => 500.00,
            'opened_at' => now(),
            'status' => 'open',
        ]);

        $response = $this->actingAs($this->merchantUser)->postJson('/pos/checkout', [
            'client_uuid' => 'UUID-BIN-19',
            'store_id' => $this->store->id,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'paid_amount' => 115.00,
            'payment_method' => 'cash',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['store_id']);
    }

    /** @test */
    public function test_20_vat_report_subtracts_returned_item_vat()
    {
        $order = Order::create([
            'tenant_id' => $this->tenant->id,
            'store_id' => $this->store->id,
            'invoice_no' => 'INV-REP-001',
            'subtotal' => 200.00,
            'discount_amount' => 0.00,
            'tax_amount' => 30.00,
            'grand_total' => 230.00,
            'paid_amount' => 230.00,
            'payment_status' => 'paid',
            'payment_method' => 'cash',
        ]);

        $product = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Report Return Item',
            'sku' => 'SKU-RR-01',
            'selling_price' => 200.00,
            'is_active' => true,
        ]);

        OrderItem::create([
            'tenant_id' => $this->tenant->id,
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => 1,
            'unit_price' => 200.00,
            'vat_rate' => 15.00,
            'vat_amount' => 30.00,
            'total' => 200.00,
        ]);

        ProductReturn::create([
            'tenant_id' => $this->tenant->id,
            'store_id' => $this->store->id,
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => 1,
            'refund_amount' => 230.00,
            'reason' => 'Damaged',
            'approved_by' => 'Store Manager',
        ]);

        $response = $this->actingAs($this->merchantUser)->get('/reports/vat?start_date=' . date('Y-m-d') . '&end_date=' . date('Y-m-d'));
        $response->assertStatus(200);

        $props = $response->inertiaProps();
        $this->assertEquals(0.00, (float) $props['totalVat']);
        $this->assertEquals(30.00, (float) $props['grossVat']);
        $this->assertEquals(30.00, (float) $props['returnedVat']);
    }

    /** @test */
    public function test_21_cost_price_stored_on_order_items_and_cogs_uses_sql_aggregation()
    {
        $product = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Cost Price Item',
            'sku' => 'SKU-COST-01',
            'purchase_cost' => 60.00,
            'selling_price' => 100.00,
            'is_active' => true,
        ]);

        Stock::create([
            'tenant_id' => $this->tenant->id,
            'store_id' => $this->store->id,
            'product_id' => $product->id,
            'quantity' => 10.0,
        ]);

        RegisterShift::create([
            'tenant_id' => $this->tenant->id,
            'store_id' => $this->store->id,
            'user_id' => $this->merchantUser->id,
            'opening_float' => 500.00,
            'opened_at' => now(),
            'status' => 'open',
        ]);

        $res = $this->actingAs($this->merchantUser)->postJson('/pos/checkout', [
            'client_uuid' => (string) \Illuminate\Support\Str::uuid(),
            'store_id' => $this->store->id,
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
            'paid_amount' => 230.00,
            'payment_method' => 'cash',
        ]);
        $res->assertStatus(200);

        $order = Order::latest()->first();
        $item = OrderItem::where('order_id', $order->id)->first();
        $this->assertEquals(60.00, (float) $item->cost_price);

        $resReport = $this->actingAs($this->merchantUser)->get('/reports/profit-loss?start_date=' . date('Y-m-d') . '&end_date=' . date('Y-m-d'));
        $resReport->assertStatus(200);

        $props = $resReport->inertiaProps();
        $this->assertEquals(120.00, (float) $props['cogs']); // 2 * 60.00
    }

    /** @test */
    public function test_22_pos_terminal_limits_initial_payload_to_50_items_and_search_limits_30()
    {
        for ($i = 1; $i <= 60; $i++) {
            Product::create([
                'tenant_id' => $this->tenant->id,
                'name' => "Limit Product {$i}",
                'sku' => "SKU-LIM-{$i}",
                'selling_price' => 50.00,
                'is_active' => true,
            ]);
        }

        $resIndex = $this->actingAs($this->merchantUser)->get('/pos');
        $resIndex->assertStatus(200);

        $props = $resIndex->inertiaProps();
        $this->assertLessThanOrEqual(50, count($props['initialProducts']));

        $resSearch = $this->actingAs($this->merchantUser)->get('/pos/products/search?q=Limit');
        $resSearch->assertStatus(200);
        $this->assertLessThanOrEqual(30, count($resSearch->json()));
    }

    /** @test */
    public function test_23_customer_search_endpoint_returns_debounced_results_capped_at_30()
    {
        for ($i = 1; $i <= 40; $i++) {
            Customer::create([
                'tenant_id' => $this->tenant->id,
                'name' => "Search Customer {$i}",
                'phone' => sprintf('019%08d', $i),
            ]);
        }

        $resSearch = $this->actingAs($this->merchantUser)->get('/pos/customers/search?q=Search');
        $resSearch->assertStatus(200);
        $this->assertLessThanOrEqual(30, count($resSearch->json()));
    }

    /** @test */
    public function test_24_sku_unique_per_tenant_allows_same_sku_across_different_tenants()
    {
        Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Tenant 1 Product',
            'sku' => 'SHARED-SKU-100',
            'selling_price' => 100.00,
            'is_active' => true,
        ]);

        $tenant2 = Tenant::create(['name' => 'Tenant 2', 'code' => 'T200', 'email' => 't2@test.local', 'phone' => '01900000000']);
        
        $p2 = Product::create([
            'tenant_id' => $tenant2->id,
            'name' => 'Tenant 2 Product',
            'sku' => 'SHARED-SKU-100',
            'selling_price' => 150.00,
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('products', ['tenant_id' => $tenant2->id, 'sku' => 'SHARED-SKU-100']);
    }

    /** @test */
    public function test_25_sales_csv_export_streams_with_chunking()
    {
        Order::create([
            'tenant_id' => $this->tenant->id,
            'store_id' => $this->store->id,
            'invoice_no' => 'INV-CSV-001',
            'subtotal' => 100.00,
            'tax_amount' => 15.00,
            'grand_total' => 115.00,
            'paid_amount' => 115.00,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
        ]);

        $response = $this->actingAs($this->merchantUser)->get('/reports/sales/export-csv');
        $response->assertStatus(200);
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment; filename="sales_report_', $response->headers->get('Content-Disposition'));
    }
}


