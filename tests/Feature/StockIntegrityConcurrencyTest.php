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
}
