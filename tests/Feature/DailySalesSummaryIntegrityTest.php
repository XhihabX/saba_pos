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

    public function test_order_void_decrements_summary_table()
    {
        $tenant = Tenant::create([
            'name' => 'Void Summary Tenant',
            'code' => 'VOID-01',
            'email' => 'void@example.com',
            'subscription_status' => 'active',
            'expires_at' => now()->addYear(),
        ]);

        $store = Store::create([
            'tenant_id' => $tenant->id,
            'name' => 'Void Store',
            'code' => 'VOID-ST',
            'is_active' => true,
        ]);

        $today = date('Y-m-d');

        // Record Sale
        DailySalesSummary::recordSale(
            $tenant->id, $store->id, $today,
            100.00, 15.00, 115.00, 60.00, ['cash' => 115.00]
        );

        $summaryBefore = DailySalesSummary::where('tenant_id', $tenant->id)->where('store_id', $store->id)->where('date', $today)->first();
        $this->assertEquals(1, $summaryBefore->orders_count);
        $this->assertEquals(115.00, (float)$summaryBefore->grand_total);

        // Record Void Sale
        DailySalesSummary::recordVoidSale(
            $tenant->id, $store->id, $today,
            100.00, 15.00, 115.00, 60.00, ['cash' => 115.00]
        );

        $summaryAfter = DailySalesSummary::where('tenant_id', $tenant->id)->where('store_id', $store->id)->where('date', $today)->first();
        $this->assertEquals(0, $summaryAfter->orders_count);
        $this->assertEquals(0.00, (float)$summaryAfter->grand_total);
        $this->assertEquals(0.00, (float)$summaryAfter->cash_total);
    }

    public function test_offline_sync_idempotency_prevents_duplicate_summary_records()
    {
        $tenant = Tenant::create(['name' => 'Sync Tenant', 'code' => 'SYNC-01', 'email' => 'sync@example.com', 'subscription_status' => 'active', 'expires_at' => now()->addYear()]);
        $store = Store::create(['tenant_id' => $tenant->id, 'name' => 'Sync Store', 'code' => 'SYNC-ST', 'is_active' => true]);
        $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Sync Cashier', 'email' => 'synccashier@example.com', 'password' => bcrypt('password123'), 'role' => 'merchant', 'is_approved' => true]);
        RegisterShift::create(['tenant_id' => $tenant->id, 'store_id' => $store->id, 'user_id' => $user->id, 'opening_cash' => 500.00, 'status' => 'open', 'opened_at' => now()]);
        $product = Product::create(['tenant_id' => $tenant->id, 'name' => 'Sync Product', 'sku' => 'SKU-SYNC-1', 'selling_price' => 100.00, 'purchase_cost' => 60.00, 'vat_rate' => 15.00, 'is_active' => true]);
        DB::table('stocks')->insert(['tenant_id' => $tenant->id, 'store_id' => $store->id, 'product_id' => $product->id, 'quantity' => 100, 'created_at' => now(), 'updated_at' => now()]);

        $payload = [
            'store_id' => $store->id,
            'payment_method' => 'cash',
            'paid_amount' => 115.00,
            'client_uuid' => (string) Str::uuid(),
            'idempotency_key' => 'IDEMPOTENT-SYNC-KEY-999',
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ];

        // First Post
        $res1 = $this->actingAs($user)->postJson('/pos/checkout', $payload);
        $res1->assertStatus(200);

        // Duplicate Retry Post with same Idempotency Key
        $res2 = $this->actingAs($user)->postJson('/pos/checkout', $payload);
        $res2->assertStatus(200);

        $today = date('Y-m-d');
        $summary = DailySalesSummary::where('tenant_id', $tenant->id)->where('store_id', $store->id)->where('date', $today)->first();

        // Exactly 1 order recorded in summary
        $this->assertEquals(1, $summary->orders_count);
        $this->assertEquals(115.00, (float)$summary->grand_total);
    }

    public function test_verify_daily_summaries_command_audits_and_fixes_drift()
    {
        $tenant = Tenant::create(['name' => 'Verify Tenant', 'code' => 'VERIFY-01', 'email' => 'verify@example.com', 'subscription_status' => 'active', 'expires_at' => now()->addYear()]);
        $store = Store::create(['tenant_id' => $tenant->id, 'name' => 'Verify Store', 'code' => 'VERIFY-ST', 'is_active' => true]);
        $today = date('Y-m-d');

        Order::create([
            'tenant_id' => $tenant->id,
            'store_id' => $store->id,
            'invoice_no' => 'INV-VERIFY-01',
            'subtotal' => 200.00,
            'discount_amount' => 0.00,
            'tax_amount' => 30.00,
            'grand_total' => 230.00,
            'cogs' => 120.00,
            'paid_amount' => 230.00,
            'payment_status' => 'paid',
            'payment_method' => 'cash',
            'created_at' => "{$today} 12:00:00",
            'updated_at' => "{$today} 12:00:00",
        ]);

        // Manually create artificial drift
        DailySalesSummary::create([
            'tenant_id' => $tenant->id,
            'store_id' => $store->id,
            'date' => $today,
            'orders_count' => 1,
            'subtotal' => 100.00, // Intentional drift
            'tax_amount' => 15.00,
            'grand_total' => 115.00,
            'cogs' => 60.00,
        ]);

        // Command fails audit without --fix
        $this->artisan('pos:verify-daily-summaries')
            ->assertExitCode(1);

        // Command fixes audit with --fix
        $this->artisan('pos:verify-daily-summaries --fix')
            ->assertExitCode(0);

        $syncedSummary = DailySalesSummary::where('tenant_id', $tenant->id)->where('store_id', $store->id)->where('date', $today)->first();
        $this->assertEquals(230.00, (float)$syncedSummary->grand_total);
        $this->assertEquals(200.00, (float)$syncedSummary->subtotal);
    }

    public function test_corrupted_summary_row_is_detected_logged_and_alerted()
    {
        \Illuminate\Support\Facades\Mail::fake();

        $tenant = Tenant::create(['name' => 'Corrupt Test Tenant', 'code' => 'CORRUPT-01', 'email' => 'corrupt@example.com', 'subscription_status' => 'active', 'expires_at' => now()->addYear()]);
        $store = Store::create(['tenant_id' => $tenant->id, 'name' => 'Corrupt Store', 'code' => 'CORRUPT-ST', 'is_active' => true]);
        $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Merchant Admin', 'email' => 'merchantadmin@example.com', 'password' => bcrypt('password123'), 'role' => 'merchant', 'is_approved' => true]);

        $today = date('Y-m-d');

        Order::create([
            'tenant_id' => $tenant->id,
            'store_id' => $store->id,
            'invoice_no' => 'INV-CORRUPT-01',
            'subtotal' => 100.00,
            'discount_amount' => 0.00,
            'tax_amount' => 15.00,
            'grand_total' => 115.00,
            'cogs' => 60.00,
            'paid_amount' => 115.00,
            'payment_status' => 'paid',
            'payment_method' => 'cash',
            'created_at' => "{$today} 10:00:00",
            'updated_at' => "{$today} 10:00:00",
        ]);

        // Deliberately corrupt summary table row
        DailySalesSummary::create([
            'tenant_id' => $tenant->id,
            'store_id' => $store->id,
            'date' => $today,
            'orders_count' => 1,
            'subtotal' => 100.00,
            'tax_amount' => 15.00,
            'grand_total' => 99999.00, // Corrupted grand total
            'cogs' => 60.00,
        ]);

        // 1. Scheduled verify command detects corruption, logs error, stores alert in database table discrepancy_alerts & sends email
        $this->artisan('pos:verify-daily-summaries')
            ->assertExitCode(1);

        $dbAlert = \App\Models\DiscrepancyAlert::where('tenant_id', $tenant->id)
            ->where('store_id', $store->id)
            ->where('date', $today)
            ->whereNull('resolved_at')
            ->first();

        $this->assertNotNull($dbAlert);
        $this->assertEquals(115.00, $dbAlert->expected['grand_total']);
        $this->assertEquals(99999.00, $dbAlert->actual['grand_total']);

        // Assert Email Notification Dispatched to Tenant Owner
        \Illuminate\Support\Facades\Mail::assertSent(\App\Mail\DiscrepancyAlertMail::class, function ($mail) use ($tenant) {
            return $mail->hasTo($tenant->email);
        });

        // 2. Merchant Dashboard displays warning banner with discrepancyAlerts prop
        $dashboardResponse = $this->actingAs($user)->get('/merchant/dashboard');
        $dashboardResponse->assertStatus(200);
        $dashboardResponse->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
            ->component('Merchant/Dashboard')
            ->has('discrepancyAlerts')
        );

        // 3. Admin triggers manual recalculate endpoint ("Fix & Recalculate" action)
        $fixResponse = $this->actingAs($user)->postJson('/reports/daily-summary/recalculate', [
            'store_id' => $store->id,
            'date' => $today,
        ]);

        $fixResponse->assertStatus(200)
            ->assertJson(['success' => true]);

        // 4. Verify summary row is repaired & alert is resolved in database table
        $repairedSummary = DailySalesSummary::where('tenant_id', $tenant->id)->where('store_id', $store->id)->where('date', $today)->first();
        $this->assertEquals(115.00, (float)$repairedSummary->grand_total);

        $resolvedAlert = \App\Models\DiscrepancyAlert::find($dbAlert->id);
        $this->assertNotNull($resolvedAlert->resolved_at);

        // 5. Verification command now passes cleanly
        $this->artisan('pos:verify-daily-summaries')
            ->assertExitCode(0);
    }
}
