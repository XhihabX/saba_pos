<?php

namespace Tests\Feature;

use App\Models\DailySalesSummary;
use App\Models\Order;
use App\Models\Product;
use App\Models\RegisterShift;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class DailySalesSummaryIntegrityTest extends TestCase
{
    use DatabaseMigrations;

    public function test_sales_checkout_and_product_returns_atomically_update_summary_table()
    {
        $tenant = Tenant::create([
            'name' => 'Summary Test Tenant',
            'code' => 'SUMM-01',
            'email' => 'summary@example.com',
            'subscription_status' => 'active',
            'expires_at' => now()->addYear(),
        ]);

        $store = Store::create([
            'tenant_id' => $tenant->id,
            'name' => 'Summary Test Store',
            'code' => 'SUMM-ST',
            'is_active' => true,
            'default_tax_rate' => 15.00,
            'vat_mode' => 'exclusive',
        ]);

        $user = User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Summary Cashier',
            'email' => 'summarycashier@example.com',
            'password' => bcrypt('password123'),
            'role' => 'merchant',
            'is_approved' => true,
            'pos_pin' => bcrypt('1234'),
        ]);

        RegisterShift::create([
            'tenant_id' => $tenant->id,
            'store_id' => $store->id,
            'user_id' => $user->id,
            'opening_cash' => 500.00,
            'status' => 'open',
            'opened_at' => now(),
        ]);

        $product = Product::create([
            'tenant_id' => $tenant->id,
            'name' => 'Summary Test Product',
            'sku' => 'SKU-SUMM-1',
            'selling_price' => 100.00,
            'purchase_cost' => 60.00,
            'vat_rate' => 15.00,
            'vat_mode' => 'exclusive',
            'is_active' => true,
        ]);

        DB::table('stocks')->insert([
            'tenant_id' => $tenant->id,
            'store_id' => $store->id,
            'product_id' => $product->id,
            'quantity' => 100,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 1. Process Checkout #1
        $response1 = $this->actingAs($user)->postJson('/pos/checkout', [
            'store_id' => $store->id,
            'payment_method' => 'cash',
            'paid_amount' => 115.00,
            'discount_amount' => 0,
            'client_uuid' => (string) Str::uuid(),
            'idempotency_key' => 'SUMM-KEY-001',
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 1,
                    'discount' => 0,
                ]
            ],
        ]);
        $response1->assertStatus(200);

        // 2. Process Checkout #2 with bKash
        $mfsTx = \App\Models\MfsTransaction::create([
            'tenant_id' => $tenant->id,
            'store_id' => $store->id,
            'trx_id' => 'SUMMBKASH001',
            'payment_method' => 'bkash',
            'sender_number' => '01700000000',
            'amount' => 115.00,
            'status' => 'unclaimed',
        ]);

        $response2 = $this->actingAs($user)->postJson('/pos/checkout', [
            'store_id' => $store->id,
            'payment_method' => 'bkash',
            'paid_amount' => 115.00,
            'reference_no' => 'SUMMBKASH001',
            'discount_amount' => 0,
            'client_uuid' => (string) Str::uuid(),
            'idempotency_key' => 'SUMM-KEY-002',
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 1,
                    'discount' => 0,
                ]
            ],
        ]);
        $response2->assertStatus(200);

        $today = date('Y-m-d');
        $summary = DailySalesSummary::where('tenant_id', $tenant->id)
            ->where('store_id', $store->id)
            ->where('date', $today)
            ->first();

        $this->assertNotNull($summary);
        $this->assertEquals(2, $summary->orders_count);
        $this->assertEquals(200.00, (float) $summary->subtotal);
        $this->assertEquals(30.00, (float) $summary->tax_amount);
        $this->assertEquals(230.00, (float) $summary->grand_total);
        $this->assertEquals(120.00, (float) $summary->cogs);
        $this->assertEquals(115.00, (float) $summary->cash_total);
        $this->assertEquals(115.00, (float) $summary->bkash_total);

        // Verify summary matches direct aggregate of orders
        $directAggregate = Order::where('tenant_id', $tenant->id)
            ->where('store_id', $store->id)
            ->whereDate('created_at', $today)
            ->selectRaw('COUNT(*) as cnt, SUM(subtotal) as sub, SUM(tax_amount) as tax, SUM(grand_total) as grand, SUM(cogs) as cogs_sum')
            ->first();

        $this->assertEquals($directAggregate->cnt, $summary->orders_count);
        $this->assertEquals((float)$directAggregate->sub, (float)$summary->subtotal);
        $this->assertEquals((float)$directAggregate->tax, (float)$summary->tax_amount);
        $this->assertEquals((float)$directAggregate->grand, (float)$summary->grand_total);
        $this->assertEquals((float)$directAggregate->cogs_sum, (float)$summary->cogs);

        // 3. Process Product Return
        $order1 = Order::where('idempotency_key', 'SUMM-KEY-001')->first();
        $returnResponse = $this->actingAs($user)->post('/manager/returns', [
            'invoice_no' => $order1->invoice_no,
            'product_id' => $product->id,
            'quantity' => 1,
            'refund_amount' => 115.00,
            'reason' => 'Defective item',
        ]);
        $returnResponse->assertSessionHasNoErrors();
        $returnResponse->assertStatus(302);

        $updatedSummary = DailySalesSummary::where('tenant_id', $tenant->id)
            ->where('store_id', $store->id)
            ->where('date', $today)
            ->first();

        $this->assertEquals(115.00, (float) $updatedSummary->refunds);
    }
}
