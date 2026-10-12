<?php

namespace Tests\Feature;

use App\Mail\FlaggedOrdersThresholdMail;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\Product;
use App\Models\RegisterShift;
use App\Models\Stock;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class OfflineStockReviewTest extends TestCase
{
    use DatabaseTransactions;

    protected Tenant $tenant;
    protected Store $store;
    protected User $cashier;
    protected User $manager;
    protected User $owner;
    protected Product $product;
    protected Stock $stock;
    protected RegisterShift $shift;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Offline Review Retail',
            'code' => 'ORR-'.uniqid(),
            'email' => 'owner@orr.test',
            'subscription_status' => 'active',
            'expires_at' => now('Asia/Dhaka')->addDays(30),
            'grace_days' => 5,
        ]);

        $this->store = Store::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Main Outlet',
            'code' => 'STORE-ORR',
            'currency_symbol' => '৳',
            'default_tax_rate' => 15.00,
            'allow_negative_stock' => false,
            'is_active' => true,
        ]);

        $this->owner = User::create([
            'tenant_id' => $this->tenant->id,
            'store_id' => $this->store->id,
            'name' => 'Store Owner',
            'email' => 'owner_'.uniqid().'@orr.test',
            'password' => bcrypt('password123'),
            'role' => 'merchant',
        ]);

        $this->manager = User::create([
            'tenant_id' => $this->tenant->id,
            'store_id' => $this->store->id,
            'name' => 'Store Manager',
            'email' => 'manager_'.uniqid().'@orr.test',
            'password' => bcrypt('password123'),
            'role' => 'store_manager',
        ]);

        $this->cashier = User::create([
            'tenant_id' => $this->tenant->id,
            'store_id' => $this->store->id,
            'name' => 'Counter Cashier',
            'email' => 'cashier_'.uniqid().'@orr.test',
            'password' => bcrypt('password123'),
            'role' => 'cashier',
        ]);

        $this->product = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Wireless Gaming Mouse',
            'sku' => 'MOUSE-'.uniqid(),
            'selling_price' => 500.00,
            'purchase_cost' => 300.00,
            'vat_rate' => 15.00,
            'vat_mode' => 'exclusive',
            'is_active' => true,
        ]);

        // Stock quantity set to exactly 1.00 unit
        $this->stock = Stock::create([
            'tenant_id' => $this->tenant->id,
            'store_id' => $this->store->id,
            'product_id' => $this->product->id,
            'quantity' => 1.00,
        ]);

        $this->shift = RegisterShift::create([
            'tenant_id' => $this->tenant->id,
            'store_id' => $this->store->id,
            'user_id' => $this->cashier->id,
            'opening_float' => 1000.00,
            'opened_at' => now(),
            'status' => 'open',
        ]);
    }

    public function test_forged_offline_sync_without_idempotency_key_fails_stock_check(): void
    {
        // Cashier attempts to buy 5 mouse units (only 1 available) claiming offline sync but without idempotency key
        $response = $this->actingAs($this->cashier)->postJson('/pos/checkout', [
            'store_id' => $this->store->id,
            'client_uuid' => 'UUID-FORGED-1',
            'is_offline_sync' => true,
            'idempotency_key' => null, // MISSING idempotency key
            'client_time' => now()->subMinutes(10)->toIso8601String(),
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 5, 'discount' => 0],
            ],
            'paid_amount' => 2875.00,
            'payment_method' => 'cash',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['cart']);
        $this->assertEquals(1.00, (float) $this->stock->fresh()->quantity);
    }

    public function test_offline_sync_with_future_client_time_fails_stock_check(): void
    {
        // Cashier claims offline sync with key, but client_time is 2 hours in the future
        $response = $this->actingAs($this->cashier)->postJson('/pos/checkout', [
            'store_id' => $this->store->id,
            'client_uuid' => 'UUID-FUTURE-TIME',
            'idempotency_key' => 'KEY-FUTURE-TIME',
            'is_offline_sync' => true,
            'client_time' => now()->addHours(2)->toIso8601String(), // FUTURE TIME
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 5, 'discount' => 0],
            ],
            'paid_amount' => 2875.00,
            'payment_method' => 'cash',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['cart']);
        $this->assertEquals(1.00, (float) $this->stock->fresh()->quantity);
    }

    public function test_offline_sync_with_ten_day_old_client_time_fails_stock_check(): void
    {
        // Cashier claims offline sync with key, but client_time is 10 days old (> 7 days)
        $response = $this->actingAs($this->cashier)->postJson('/pos/checkout', [
            'store_id' => $this->store->id,
            'client_uuid' => 'UUID-STALE-10D',
            'idempotency_key' => 'KEY-STALE-10D',
            'is_offline_sync' => true,
            'client_time' => now()->subDays(10)->toIso8601String(), // 10 DAYS OLD
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 5, 'discount' => 0],
            ],
            'paid_amount' => 2875.00,
            'payment_method' => 'cash',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['cart']);
        $this->assertEquals(1.00, (float) $this->stock->fresh()->quantity);
    }

    public function test_valid_offline_sync_with_deficit_is_accepted_and_flagged_for_review(): void
    {
        // Cashier sells 3 units offline (1 in stock, deficit of 2). Meets all 4 conditions!
        $clientTime = now()->subMinutes(15)->toIso8601String();

        $response = $this->actingAs($this->cashier)->postJson('/pos/checkout', [
            'store_id' => $this->store->id,
            'client_uuid' => 'UUID-VALID-DEFICIT',
            'idempotency_key' => 'IDEMP-VALID-DEFICIT-001',
            'is_offline_sync' => true,
            'client_time' => $clientTime,
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 3, 'discount' => 0],
            ],
            'paid_amount' => 1725.00, // 3 * 500 + 15% VAT = 1725
            'payment_method' => 'cash',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        // Order asserted in DB
        $order = Order::where('tenant_id', $this->tenant->id)
            ->where('idempotency_key', 'IDEMP-VALID-DEFICIT-001')
            ->first();

        $this->assertNotNull($order);
        $this->assertTrue($order->flagged_for_review);
        $this->assertStringContainsString('Insufficient stock', $order->review_reason);
        $this->assertNull($order->resolved_at);

        // Stock was decremented from 1.00 to -2.00
        $this->assertEquals(-2.00, (float) $this->stock->fresh()->quantity);

        // Audit log created
        $audit = AuditLog::where('tenant_id', $this->tenant->id)
            ->where('action', 'order_flagged_for_review')
            ->first();
        $this->assertNotNull($audit);
        $this->assertEquals($order->invoice_no, $audit->payload['invoice_no']);
    }

    public function test_manager_can_view_needs_review_orders_and_resolve_order(): void
    {
        // Create flagged order
        $order = Order::create([
            'tenant_id' => $this->tenant->id,
            'store_id' => $this->store->id,
            'user_id' => $this->cashier->id,
            'invoice_no' => 'INV-TEST-REVIEW-001',
            'subtotal' => 1500.00,
            'grand_total' => 1725.00,
            'paid_amount' => 1725.00,
            'payment_method' => 'cash',
            'flagged_for_review' => true,
            'review_reason' => 'Insufficient stock during offline sync',
        ]);

        // Cashier cannot access manager review page (redirects to login)
        $this->actingAs($this->cashier)->get('/manager/orders/needs-review')->assertRedirect(route('login'));

        // Manager can access review page
        $res = $this->actingAs($this->manager)->get('/manager/orders/needs-review');
        $res->assertStatus(200);

        // Manager resolves the order
        $resolveRes = $this->actingAs($this->manager)->postJson("/manager/orders/{$order->id}/resolve", [
            'notes' => 'Discrepancy counted; restock adjustment completed',
        ]);

        $resolveRes->assertStatus(200);
        $resolveRes->assertJson(['success' => true]);

        $order->refresh();
        $this->assertNotNull($order->resolved_at);
        $this->assertEquals($this->manager->id, $order->resolved_by);
        $this->assertStringContainsString('restock adjustment completed', $order->notes);

        // Audit log verified
        $resolveAudit = AuditLog::where('tenant_id', $this->tenant->id)
            ->where('action', 'order_review_resolved')
            ->first();
        $this->assertNotNull($resolveAudit);
        $this->assertEquals($order->invoice_no, $resolveAudit->payload['invoice_no']);
    }

    public function test_threshold_email_sent_when_store_exceeds_five_flagged_orders_in_day(): void
    {
        Mail::fake();

        // Pre-create 5 flagged orders for today
        for ($i = 1; $i <= 5; $i++) {
            Order::create([
                'tenant_id' => $this->tenant->id,
                'store_id' => $this->store->id,
                'user_id' => $this->cashier->id,
                'invoice_no' => "INV-PRE-{$i}",
                'subtotal' => 500.00,
                'grand_total' => 575.00,
                'paid_amount' => 575.00,
                'payment_method' => 'cash',
                'flagged_for_review' => true,
                'review_reason' => 'Insufficient stock during offline sync',
                'created_at' => now(),
            ]);
        }

        // 6th offline deficit sync triggers threshold > 5
        $response = $this->actingAs($this->cashier)->postJson('/pos/checkout', [
            'store_id' => $this->store->id,
            'client_uuid' => 'UUID-6TH-DEFICIT',
            'idempotency_key' => 'IDEMP-6TH-DEFICIT',
            'is_offline_sync' => true,
            'client_time' => now()->subMinutes(5)->toIso8601String(),
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 10, 'discount' => 0],
            ],
            'paid_amount' => 5750.00,
            'payment_method' => 'cash',
        ]);

        $response->assertStatus(200);

        // Assert threshold email queued for manager and merchant owner
        Mail::assertQueued(FlaggedOrdersThresholdMail::class, function ($mail) {
            return $mail->flaggedCount === 6 && $mail->store->id === $this->store->id;
        });
    }
}
