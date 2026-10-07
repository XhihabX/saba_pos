<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\RegisterShift;
use App\Models\Stock;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MultiTenantIsolationSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenantA;
    protected User $merchantA;
    protected Store $storeA;
    protected Product $productA;
    protected Customer $customerA;
    protected Order $orderA;

    protected Tenant $tenantB;
    protected User $merchantB;
    protected Store $storeB;
    protected Product $productB;
    protected Customer $customerB;
    protected Order $orderB;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('services.mfs.secret_key', 'mfs_test_secret_999');

        // Tenant A Setup
        $this->tenantA = Tenant::create([
            'name' => 'Merchant A Enterprise',
            'code' => 'TA01',
            'email' => 'merchantA@test.local',
            'phone' => '01711111111',
            'subscription_status' => 'active',
            'expires_at' => now()->addDays(30),
        ]);

        $this->storeA = Store::create([
            'tenant_id' => $this->tenantA->id,
            'code' => 'ST-A01',
            'name' => 'Store A',
            'phone' => '01711111111',
            'address' => 'Dhaka Branch A',
            'default_tax_rate' => 15.0,
            'is_active' => true,
        ]);

        $this->merchantA = User::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'store_id' => $this->storeA->id,
            'role' => 'merchant',
            'pos_pin' => Hash::make('1111'),
        ]);

        $this->productA = Product::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Product A Secret Item',
            'sku' => 'SKU-A100',
            'selling_price' => 200.00,
            'purchase_cost' => 150.00,
            'is_active' => true,
        ]);

        $this->customerA = Customer::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Customer A Private VIP',
            'phone' => '01711111111',
        ]);

        $this->orderA = Order::create([
            'tenant_id' => $this->tenantA->id,
            'store_id' => $this->storeA->id,
            'customer_id' => $this->customerA->id,
            'user_id' => $this->merchantA->id,
            'invoice_no' => 'INV-TENANT-A-999',
            'subtotal' => 200.00,
            'discount_amount' => 0.00,
            'tax_amount' => 30.00,
            'grand_total' => 230.00,
            'paid_amount' => 230.00,
            'payment_status' => 'paid',
            'payment_method' => 'cash',
        ]);

        OrderItem::create([
            'tenant_id' => $this->tenantA->id,
            'order_id' => $this->orderA->id,
            'product_id' => $this->productA->id,
            'product_name' => $this->productA->name,
            'quantity' => 1,
            'unit_price' => 200.00,
            'cost_price' => 150.00,
            'vat_rate' => 15.00,
            'vat_amount' => 30.00,
            'total' => 200.00,
        ]);

        // Tenant B Setup
        $this->tenantB = Tenant::create([
            'name' => 'Merchant B Outlet',
            'code' => 'TB02',
            'email' => 'merchantB@test.local',
            'phone' => '01822222222',
            'subscription_status' => 'active',
            'expires_at' => now()->addDays(30),
        ]);

        $this->storeB = Store::create([
            'tenant_id' => $this->tenantB->id,
            'code' => 'ST-B02',
            'name' => 'Store B',
            'phone' => '01822222222',
            'address' => 'Chittagong Branch B',
            'default_tax_rate' => 15.0,
            'is_active' => true,
        ]);

        $this->merchantB = User::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'store_id' => $this->storeB->id,
            'role' => 'merchant',
            'pos_pin' => Hash::make('2222'),
        ]);

        $this->productB = Product::create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'Product B Public Item',
            'sku' => 'SKU-B200',
            'selling_price' => 500.00,
            'purchase_cost' => 400.00,
            'is_active' => true,
        ]);

        $this->customerB = Customer::create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'Customer B Client',
            'phone' => '01822222222',
        ]);

        $this->orderB = Order::create([
            'tenant_id' => $this->tenantB->id,
            'store_id' => $this->storeB->id,
            'customer_id' => $this->customerB->id,
            'user_id' => $this->merchantB->id,
            'invoice_no' => 'INV-TENANT-B-888',
            'subtotal' => 500.00,
            'discount_amount' => 0.00,
            'tax_amount' => 75.00,
            'grand_total' => 575.00,
            'paid_amount' => 575.00,
            'payment_status' => 'paid',
            'payment_method' => 'cash',
        ]);

        OrderItem::create([
            'tenant_id' => $this->tenantB->id,
            'order_id' => $this->orderB->id,
            'product_id' => $this->productB->id,
            'product_name' => $this->productB->name,
            'quantity' => 1,
            'unit_price' => 500.00,
            'cost_price' => 400.00,
            'vat_rate' => 15.00,
            'vat_amount' => 75.00,
            'total' => 500.00,
        ]);
    }

    /** @test */
    public function test_01_merchant_b_cannot_see_merchant_a_stores_or_orders_in_lists()
    {
        $this->actingAs($this->merchantB);

        $resStores = $this->get('/merchant/stores');
        $resStores->assertStatus(200);
        $resStores->assertDontSee($this->storeA->name);

        $resOrders = $this->get('/merchant/orders');
        $resOrders->assertStatus(200);
        $resOrders->assertDontSee($this->orderA->invoice_no);

        $resCustomers = $this->get('/merchant/customers');
        $resCustomers->assertStatus(200);
        $resCustomers->assertDontSee($this->customerA->name);
    }

    /** @test */
    public function test_02_merchant_b_cannot_access_merchant_a_pdf_invoice_or_mushak63()
    {
        $this->actingAs($this->merchantB);

        // Accessing Tenant A invoice PDF returns 404
        $resPdf = $this->get("/pos/invoice/{$this->orderA->id}/pdf");
        $resPdf->assertStatus(404);

        // Accessing Tenant A Mushak 6.3 returns 404
        $resMushak = $this->get("/vat/mushak-6.3/{$this->orderA->id}");
        $resMushak->assertStatus(404);
    }

    /** @test */
    public function test_03_merchant_b_cannot_checkout_using_merchant_a_store_or_product()
    {
        $this->actingAs($this->merchantB);

        RegisterShift::create([
            'tenant_id' => $this->tenantB->id,
            'store_id' => $this->storeB->id,
            'user_id' => $this->merchantB->id,
            'opening_float' => 1000.00,
            'opened_at' => now(),
            'status' => 'open',
        ]);

        // Attempt checkout with Store A (cross-tenant store ID)
        $resStoreA = $this->postJson('/pos/checkout', [
            'store_id' => $this->storeA->id,
            'items' => [['product_id' => $this->productB->id, 'quantity' => 1]],
            'paid_amount' => 575.00,
            'payment_method' => 'cash',
        ]);
        $resStoreA->assertStatus(422)->assertJsonValidationErrors(['store_id']);

        // Attempt checkout with Product A (cross-tenant product ID) returns 404 Not Found
        $resProductA = $this->postJson('/pos/checkout', [
            'store_id' => $this->storeB->id,
            'items' => [['product_id' => $this->productA->id, 'quantity' => 1]],
            'paid_amount' => 230.00,
            'payment_method' => 'cash',
        ]);
        $resProductA->assertStatus(404);
    }

    /** @test */
    public function test_04_merchant_b_cannot_process_product_return_against_merchant_a_invoice()
    {
        $this->actingAs($this->merchantB);

        $resReturn = $this->postJson('/manager/returns', [
            'invoice_no' => $this->orderA->invoice_no,
            'product_id' => $this->productA->id,
            'quantity' => 1,
            'refund_amount' => 230.00,
            'reason' => 'Cross tenant return attempt',
        ]);

        $resReturn->assertStatus(404);
    }

    /** @test */
    public function test_05_csv_export_for_merchant_b_excludes_merchant_a_orders()
    {
        $this->actingAs($this->merchantB);

        $response = $this->get('/reports/sales/export-csv');
        $response->assertStatus(200);

        $content = $response->streamedContent();
        $this->assertStringContainsString($this->orderB->invoice_no, $content);
        $this->assertStringNotContainsString($this->orderA->invoice_no, $content);
    }
}
