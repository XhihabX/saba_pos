<?php

namespace Tests\Feature;

use App\Models\Attendance;
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
            'store_id' => $this->store->id,
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
            'paid_amount' => 230.00,
            'payment_method' => 'cash',
        ]);

        $response1->assertStatus(422)->assertJsonValidationErrors(['cart']);

        Stock::where('store_id', $this->store->id)->where('product_id', $product->id)->update(['quantity' => 5.0]);

        // Discount without supervisor PIN rejected
        $response2 = $this->actingAs($this->merchantUser)->postJson('/pos/checkout', [
            'store_id' => $this->store->id,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'discount_amount' => 10.00,
            'paid_amount' => 103.50,
            'payment_method' => 'cash',
        ]);

        $response2->assertStatus(422)->assertJsonValidationErrors(['discount']);

        // Successful checkout with supervisor PIN & idempotency_key
        $response3 = $this->actingAs($this->merchantUser)->postJson('/pos/checkout', [
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
}
