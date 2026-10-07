<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\RegisterShift;
use App\Models\Stock;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class OfflineSalesSyncTest extends TestCase
{
    use RefreshDatabase;

    protected $merchant;
    protected $tenant;
    protected $store;
    protected $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Offline Sync Tenant',
            'code' => 'TEN-OFFLINE-01',
            'email' => 'offlinetenant@example.com',
            'subscription_status' => 'active',
            'expires_at' => now()->addYear(),
        ]);



        $this->merchant = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Offline Cashier',
            'email' => 'offlinecashier@example.com',
            'password' => bcrypt('secret123'),
            'role' => 'merchant',
            'is_approved' => true,
        ]);

        $this->store = Store::create([
            'tenant_id' => $this->tenant->id,
            'code' => 'BR-OFF',
            'name' => 'Offline Branch Outlet',
            'bin_number' => '5544332211',
            'is_vat_registered' => true,
            'allow_negative_stock' => false,
        ]);


        $this->product = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Offline Product Item',
            'sku' => 'OFFLINE-001',
            'purchase_cost' => 100.00,
            'selling_price' => 200.00,
            'vat_rate' => 15.0,
            'is_active' => true,
        ]);

        Stock::create([
            'tenant_id' => $this->tenant->id,
            'store_id' => $this->store->id,
            'product_id' => $this->product->id,
            'quantity' => 50,
        ]);

        RegisterShift::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->merchant->id,
            'store_id' => $this->store->id,
            'opening_balance' => 1000.00,
            'status' => 'open',
            'opened_at' => now(),
        ]);
    }

    public function test_offline_mode_queues_3_sales_syncs_upon_reconnect_and_prevents_duplicates_on_retry()
    {
        // Generate 3 unique idempotency keys representing 3 offline sales queued on client
        $offlineKey1 = 'OFFLINE-UUID-' . Str::uuid();
        $offlineKey2 = 'OFFLINE-UUID-' . Str::uuid();
        $offlineKey3 = 'OFFLINE-UUID-' . Str::uuid();

        $sale1 = [
            'store_id' => $this->store->id,
            'client_uuid' => $offlineKey1,
            'idempotency_key' => $offlineKey1,
            'items' => [['product_id' => $this->product->id, 'quantity' => 1]],
            'paid_amount' => 230.00,
            'payment_method' => 'cash',
        ];

        $sale2 = [
            'store_id' => $this->store->id,
            'client_uuid' => $offlineKey2,
            'idempotency_key' => $offlineKey2,
            'items' => [['product_id' => $this->product->id, 'quantity' => 1]],
            'paid_amount' => 230.00,
            'payment_method' => 'cash',
        ];

        $sale3 = [
            'store_id' => $this->store->id,
            'client_uuid' => $offlineKey3,
            'idempotency_key' => $offlineKey3,
            'items' => [['product_id' => $this->product->id, 'quantity' => 1]],
            'paid_amount' => 230.00,
            'payment_method' => 'cash',
        ];

        // 1. Send all 3 queued offline sales upon network reconnection
        $resp1 = $this->actingAs($this->merchant)->postJson('/pos/checkout', $sale1);
        $resp2 = $this->actingAs($this->merchant)->postJson('/pos/checkout', $sale2);
        $resp3 = $this->actingAs($this->merchant)->postJson('/pos/checkout', $sale3);

        $resp1->assertStatus(200);
        $resp2->assertStatus(200);
        $resp3->assertStatus(200);

        // 2. Assert exactly 3 orders created in database
        $this->assertEquals(3, Order::where('tenant_id', $this->tenant->id)->count());

        // 3. Assert stock correctly decremented from 50 to 47 (3 sales of 1 item)
        $this->assertDatabaseHas('stocks', [
            'store_id' => $this->store->id,
            'product_id' => $this->product->id,
            'quantity' => 47,
        ]);

        // 4. Simulate a sync retry (client retries posting sale1 and sale2 due to network glitch)
        $retryResp1 = $this->actingAs($this->merchant)->postJson('/pos/checkout', $sale1);
        $retryResp2 = $this->actingAs($this->merchant)->postJson('/pos/checkout', $sale2);

        $retryResp1->assertStatus(200)->assertJson(['message' => 'Order already processed (idempotent)']);
        $retryResp2->assertStatus(200)->assertJson(['message' => 'Order already processed (idempotent)']);

        // Assert total orders remains exactly 3 and stock remains 47 without double-deduction
        $this->assertEquals(3, Order::where('tenant_id', $this->tenant->id)->count());
        $this->assertDatabaseHas('stocks', [
            'store_id' => $this->store->id,
            'product_id' => $this->product->id,
            'quantity' => 47,
        ]);
    }
}
