<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\Stock;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class StockIntegrityConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_with_no_stock_row_cannot_be_sold_when_allow_negative_stock_is_false(): void
    {
        $tenant = Tenant::create([
            'name' => 'Stock Merchant',
            'code' => 'stock-merchant-test',
            'email' => 'stock.merchant@example.com',
            'status' => 'active',
            'subscription_status' => 'active',
            'subscription_expires_at' => now()->addDays(30),
        ]);

        $store = Store::create([
            'tenant_id' => $tenant->id,
            'name' => 'Branch Store 1',
            'code' => 'STORE-01',
            'allow_negative_stock' => false,
            'default_tax_rate' => 15.00,
        ]);

        $user = User::create([
            'tenant_id' => $tenant->id,
            'store_id' => $store->id,
            'name' => 'Cashier User',
            'email' => 'cashier.stock@example.com',
            'password' => Hash::make('password123'),
            'role' => 'cashier',
        ]);

        $product = Product::create([
            'tenant_id' => $tenant->id,
            'name' => 'Zero Stock Item',
            'sku' => 'ZERO-STOCK-001',
            'selling_price' => 50.00,
            'purchase_cost' => 30.00,
            'is_active' => true,
        ]);

        \App\Models\RegisterShift::create([
            'tenant_id' => $tenant->id,
            'store_id' => $store->id,
            'user_id' => $user->id,
            'opening_cash' => 100.00,
            'status' => 'open',
            'opened_at' => now(),
        ]);

        $this->actingAs($user);

        // Checkout attempt for product with zero stock
        $response = $this->postJson('/pos/checkout', [
            'client_uuid' => 'UUID-STOCK-ZERO-001',
            'store_id' => $store->id,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2],
            ],
            'paid_amount' => 115.00,
            'payment_method' => 'cash',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['cart']);

        // Stock quantity remains 0
        $stock = Stock::where('store_id', $store->id)->where('product_id', $product->id)->first();
        $this->assertEquals(0.00, (float) ($stock?->quantity ?? 0));
    }

    public function test_idempotency_key_inside_transaction_returns_original_order_without_duplicate_stock_deduction(): void
    {
        $tenant = Tenant::create([
            'name' => 'Idempotency Merchant',
            'code' => 'idemp-merchant',
            'email' => 'idemp@example.com',
            'status' => 'active',
            'subscription_status' => 'active',
            'subscription_expires_at' => now()->addDays(30),
        ]);

        $store = Store::create([
            'tenant_id' => $tenant->id,
            'name' => 'Branch Store 2',
            'code' => 'STORE-02',
            'allow_negative_stock' => false,
            'default_tax_rate' => 15.00,
        ]);

        $user = User::create([
            'tenant_id' => $tenant->id,
            'store_id' => $store->id,
            'name' => 'Cashier Idemp',
            'email' => 'idemp.user@example.com',
            'password' => Hash::make('password123'),
            'role' => 'cashier',
        ]);

        $product = Product::create([
            'tenant_id' => $tenant->id,
            'name' => 'Stocked Item',
            'sku' => 'STOCKED-001',
            'selling_price' => 100.00,
            'purchase_cost' => 60.00,
            'is_active' => true,
        ]);

        Stock::create([
            'tenant_id' => $tenant->id,
            'store_id' => $store->id,
            'product_id' => $product->id,
            'quantity' => 10.00,
        ]);

        \App\Models\RegisterShift::create([
            'tenant_id' => $tenant->id,
            'store_id' => $store->id,
            'user_id' => $user->id,
            'opening_cash' => 100.00,
            'status' => 'open',
            'opened_at' => now(),
        ]);

        $this->actingAs($user);

        $idempotencyKey = 'IDEM-TEST-UNIQUE-KEY-001';

        // 1st request
        $res1 = $this->postJson('/pos/checkout', [
            'client_uuid' => $idempotencyKey,
            'store_id' => $store->id,
            'idempotency_key' => $idempotencyKey,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 3],
            ],
            'paid_amount' => 345.00,
            'payment_method' => 'cash',
        ]);

        $res1->assertStatus(200);
        $orderId = $res1->json('order.id');

        // Stock deducted by 3 -> 7 remaining
        $stock = Stock::where('store_id', $store->id)->where('product_id', $product->id)->first();
        $this->assertEquals(7.00, (float) $stock->quantity);

        // 2nd identical request with same idempotency_key
        $res2 = $this->postJson('/pos/checkout', [
            'client_uuid' => $idempotencyKey,
            'store_id' => $store->id,
            'idempotency_key' => $idempotencyKey,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 3],
            ],
            'paid_amount' => 345.00,
            'payment_method' => 'cash',
        ]);

        $res2->assertStatus(200);
        $this->assertEquals($orderId, $res2->json('order.id'));

        // Stock remains 7, exactly 1 order in DB
        $stockReload = Stock::where('store_id', $store->id)->where('product_id', $product->id)->first();
        $this->assertEquals(7.00, (float) $stockReload->quantity);

        $this->assertEquals(1, Order::where('idempotency_key', $idempotencyKey)->count());
    }

    /**
     * INVARIANT EXCEPTION DECLARATION:
     * While allow_negative_stock=false strictly rejects online checkouts when available < quantity,
     * the SINGLE ALLOWED EXCEPTION is an authentic offline queue sync (is_offline_sync=true,
     * valid idempotency_key, client_time not future and <= 7 days old) where physical goods
     * were already handed to the customer offline. This exception decrements stock and flags
     * the order for review (flagged_for_review=true) so inventory is reconciled rather than dropping sales.
     */
    public function test_offline_sync_deficit_is_single_allowed_stock_invariant_exception_and_flags_order_for_review(): void
    {
        $tenant = Tenant::create([
            'name' => 'Offline Invariant Tenant',
            'code' => 'OIT-'.uniqid(),
            'email' => 'oit_'.uniqid().'@test.local',
            'status' => 'active',
            'subscription_status' => 'active',
            'expires_at' => now()->addDays(30),
        ]);

        $store = Store::create([
            'tenant_id' => $tenant->id,
            'name' => 'Branch Store 1',
            'code' => 'STORE-OIT-'.uniqid(),
            'allow_negative_stock' => false,
            'default_tax_rate' => 15.00,
        ]);

        $user = User::create([
            'tenant_id' => $tenant->id,
            'store_id' => $store->id,
            'name' => 'Cashier OIT',
            'email' => 'cashier.oit_'.uniqid().'@test.local',
            'password' => Hash::make('password123'),
            'role' => 'cashier',
        ]);

        $product = Product::create([
            'tenant_id' => $tenant->id,
            'name' => 'Stocked Product',
            'sku' => 'SKU-OIT-'.uniqid(),
            'selling_price' => 100.00,
            'purchase_cost' => 60.00,
            'is_active' => true,
        ]);

        Stock::create([
            'tenant_id' => $tenant->id,
            'store_id' => $store->id,
            'product_id' => $product->id,
            'quantity' => 2.00,
        ]);

        \App\Models\RegisterShift::create([
            'tenant_id' => $tenant->id,
            'store_id' => $store->id,
            'user_id' => $user->id,
            'opening_float' => 500.00,
            'opened_at' => now(),
            'status' => 'open',
        ]);

        $this->actingAs($user);

        // 1. Regular online checkout requesting 5 units (exceeding stock of 2) is STRICTLY REJECTED
        $onlineRes = $this->postJson('/pos/checkout', [
            'client_uuid' => 'UUID-ONLINE-FAIL',
            'store_id' => $store->id,
            'items' => [['product_id' => $product->id, 'quantity' => 5]],
            'paid_amount' => 575.00,
            'payment_method' => 'cash',
        ]);
        $onlineRes->assertStatus(422);

        // 2. Offline sync with key & valid client_time is ACCEPTED under the single allowed exception
        $offlineRes = $this->postJson('/pos/checkout', [
            'client_uuid' => 'UUID-OFFLINE-ALLOWED',
            'store_id' => $store->id,
            'idempotency_key' => 'IDEMP-OFFLINE-ALLOWED',
            'is_offline_sync' => true,
            'client_time' => now()->subMinutes(10)->toIso8601String(),
            'items' => [['product_id' => $product->id, 'quantity' => 5]],
            'paid_amount' => 575.00,
            'payment_method' => 'cash',
        ]);
        $offlineRes->assertStatus(200);

        $order = Order::where('idempotency_key', 'IDEMP-OFFLINE-ALLOWED')->first();
        $this->assertNotNull($order);
        $this->assertTrue($order->flagged_for_review);
        $this->assertStringContainsString('Insufficient stock', $order->review_reason);

        // Stock reflects physical deficit (-3) awaiting manager reconciliation
        $stock = Stock::where('store_id', $store->id)->where('product_id', $product->id)->first();
        $this->assertEquals(-3.00, (float) $stock->quantity);
    }
}
