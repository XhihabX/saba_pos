<?php

namespace Tests\Feature;

use App\Mail\DiscrepancyAlertMail;
use App\Models\DiscrepancyAlert;
use App\Models\MfsTransaction;
use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\Product;
use App\Models\RegisterShift;
use App\Models\Stock;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class MfsReconciliationAndStockPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected Store $store;

    protected User $merchant;

    protected User $manager;

    protected User $cashier;

    protected Product $product;

    protected RegisterShift $shift;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.mfs.secret_key', 'test_secret_12345');

        $this->tenant = Tenant::create([
            'name' => 'MFS Policy Tenant',
            'code' => 'MPT-'.uniqid(),
            'email' => 'owner@mfs-policy.test',
            'subscription_status' => 'active',
            'expires_at' => now()->addDays(30),
            'mfs_webhook_secret' => 'test_secret_12345',
        ]);

        $this->store = Store::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Flagship Store',
            'code' => 'STORE-FS-01',
            'currency_symbol' => '৳',
            'default_tax_rate' => 15.00,
            'allow_negative_stock' => false,
            'shelf_pickup_mode' => false,
            'max_counter_verified_amount' => 5000.00,
            'max_counter_verified_per_shift' => 10,
            'is_active' => true,
        ]);

        $this->merchant = User::create([
            'tenant_id' => $this->tenant->id,
            'store_id' => $this->store->id,
            'name' => 'Merchant CEO',
            'email' => 'merchant_'.uniqid().'@test.local',
            'password' => bcrypt('password'),
            'role' => 'merchant',
            'pos_pin' => Hash::make('9999'),
        ]);

        $this->manager = User::create([
            'tenant_id' => $this->tenant->id,
            'store_id' => $this->store->id,
            'name' => 'Branch Supervisor',
            'email' => 'manager_'.uniqid().'@test.local',
            'password' => bcrypt('password'),
            'role' => 'store_manager',
            'pos_pin' => Hash::make('8888'),
        ]);

        $this->cashier = User::create([
            'tenant_id' => $this->tenant->id,
            'store_id' => $this->store->id,
            'name' => 'Front Cashier',
            'email' => 'cashier_'.uniqid().'@test.local',
            'password' => bcrypt('password'),
            'role' => 'cashier',
            'pos_pin' => Hash::make('1234'),
        ]);

        $this->shift = RegisterShift::create([
            'tenant_id' => $this->tenant->id,
            'store_id' => $this->store->id,
            'user_id' => $this->cashier->id,
            'opening_cash' => 1000.00,
            'opened_at' => now(),
            'status' => 'open',
        ]);

        $this->product = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Retail Grocery Item',
            'sku' => 'SKU-GROCERY-'.uniqid(),
            'selling_price' => 100.00,
            'purchase_cost' => 60.00,
            'vat_rate' => 15.00,
            'vat_mode' => 'exclusive',
            'is_active' => true,
        ]);

        Stock::create([
            'tenant_id' => $this->tenant->id,
            'store_id' => $this->store->id,
            'product_id' => $this->product->id,
            'quantity' => 50.00,
        ]);
    }

    /** @test */
    public function test_1a_manager_can_access_mfs_reconciliation_page_and_cashier_is_forbidden(): void
    {
        // Cashier redirected / unauthorized
        $resCashier = $this->actingAs($this->cashier)->get('/manager/mfs-reconciliation');
        $resCashier->assertRedirect(route('login'));

        // Manager permitted (HTTP 200)
        $resManager = $this->actingAs($this->manager)->get('/manager/mfs-reconciliation');
        $resManager->assertStatus(200);

        // Merchant permitted (HTTP 200)
        $resMerchant = $this->actingAs($this->merchant)->get('/manager/mfs-reconciliation');
        $resMerchant->assertStatus(200);
    }

    /** @test */
    public function test_1a_reconciliation_page_lists_counter_verified_payments_with_cashier_amount_trxid_time_and_status(): void
    {
        // Create an order with counter-verified payment
        $order = Order::create([
            'tenant_id' => $this->tenant->id,
            'store_id' => $this->store->id,
            'user_id' => $this->cashier->id,
            'invoice_no' => 'INV-TEST-001',
            'subtotal' => 100.00,
            'grand_total' => 115.00,
            'paid_amount' => 115.00,
            'payment_status' => 'paid',
            'payment_method' => 'bkash',
        ]);

        $payment = OrderPayment::create([
            'tenant_id' => $this->tenant->id,
            'order_id' => $order->id,
            'payment_method' => 'bkash',
            'amount' => 115.00,
            'reference_no' => 'BKASH-TRX-101',
            'verification_status' => 'counter_verified',
        ]);

        $res = $this->actingAs($this->manager)->get('/manager/mfs-reconciliation');
        $res->assertStatus(200);

        $page = $res->viewData('page');
        $payments = $page['props']['payments']['data'];
        $this->assertNotEmpty($payments);

        $row = collect($payments)->firstWhere('trx_id', 'BKASH-TRX-101');
        $this->assertNotNull($row);
        $this->assertEquals('Front Cashier', $row['cashier_name']);
        $this->assertEquals(115.00, $row['amount']);
        $this->assertEquals('BKASH-TRX-101', $row['trx_id']);
        $this->assertEquals('unmatched', $row['status']);
        $this->assertFalse($row['is_matched']);
    }

    /** @test */
    public function test_1b_auto_match_when_webhook_arrives_later_and_marks_payment_auto_verified(): void
    {
        // Cashier completes checkout with counter-verified MFS
        $checkoutRes = $this->actingAs($this->cashier)->postJson('/pos/checkout', [
            'client_uuid' => (string) Str::uuid(),
            'store_id' => $this->store->id,
            'items' => [['product_id' => $this->product->id, 'quantity' => 1]],
            'paid_amount' => 115.00,
            'payment_method' => 'bkash',
            'reference_no' => 'TRX-LATE-WEBHOOK-99',
        ]);
        $checkoutRes->assertStatus(200);

        $payment = OrderPayment::where('reference_no', 'TRX-LATE-WEBHOOK-99')->firstOrFail();
        $this->assertEquals('counter_verified', $payment->verification_status);

        // Later, Android webhook arrives with matching TrxID and amount
        $ts = time();
        $trxId = 'TRX-LATE-WEBHOOK-99';
        $amount = 115.00;
        $sig = hash_hmac('sha256', "{$ts}.{$trxId}.{$amount}", 'test_secret_12345');

        $webhookRes = $this->postJson('/api/v1/mfs-webhook', [
            'tenant_id' => $this->tenant->id,
            'trx_id' => $trxId,
            'amount' => $amount,
            'gateway' => 'bkash',
            'timestamp' => $ts,
            'signature' => $sig,
        ], [
            'X-MFS-Tenant-ID' => $this->tenant->id,
            'X-MFS-Timestamp' => $ts,
            'X-MFS-Signature' => $sig,
        ]);

        $webhookRes->assertStatus(201);

        // Assert payment is now auto_verified
        $payment->refresh();
        $this->assertEquals('auto_verified', $payment->verification_status);

        // Assert MFS transaction record is claimed with order_id
        $mfsTx = MfsTransaction::where('trx_id', $trxId)->firstOrFail();
        $this->assertEquals('claimed', $mfsTx->status);
        $this->assertEquals($payment->order_id, $mfsTx->order_id);
    }

    /** @test */
    public function test_1c_payments_unmatched_after_24h_create_discrepancy_alert_and_email_owner(): void
    {
        Mail::fake();

        $order = Order::create([
            'tenant_id' => $this->tenant->id,
            'store_id' => $this->store->id,
            'user_id' => $this->cashier->id,
            'invoice_no' => 'INV-UNMATCHED-24H',
            'subtotal' => 200.00,
            'grand_total' => 230.00,
            'paid_amount' => 230.00,
            'payment_status' => 'paid',
            'payment_method' => 'bkash',
        ]);

        $payment = OrderPayment::create([
            'tenant_id' => $this->tenant->id,
            'order_id' => $order->id,
            'payment_method' => 'bkash',
            'amount' => 230.00,
            'reference_no' => 'TRX-UNMATCHED-24H',
            'verification_status' => 'counter_verified',
        ]);
        $payment->created_at = now()->subHours(25);
        $payment->save();

        // Run scanner command
        $this->artisan('pos:check-unmatched-mfs')->assertSuccessful();

        // Assert DiscrepancyAlert created
        $alert = DiscrepancyAlert::where('tenant_id', $this->tenant->id)
            ->where('store_id', $this->store->id)
            ->first();

        $this->assertNotNull($alert);
        $this->assertEquals('TRX-UNMATCHED-24H', $alert->expected['trx_id']);
        $this->assertEquals(230.00, (float) $alert->expected['amount']);

        // Assert Mail sent to owner
        Mail::assertSent(DiscrepancyAlertMail::class, function ($mail) {
            return $mail->hasTo('owner@mfs-policy.test');
        });

        // Assert reconciliation page marks it as is_unmatched_24h
        $res = $this->actingAs($this->manager)->get('/manager/mfs-reconciliation');
        $res->assertStatus(200);
        $payments = $res->viewData('page')['props']['payments']['data'];
        $row = collect($payments)->firstWhere('trx_id', 'TRX-UNMATCHED-24H');
        $this->assertNotNull($row);
        $this->assertTrue($row['is_unmatched_24h']);
    }

    /** @test */
    public function test_1d_counter_verified_amount_exceeding_threshold_requires_supervisor_pin(): void
    {
        $this->store->update(['max_counter_verified_amount' => 5000.00]);

        // 1. Amount ৳6,000 without supervisor PIN fails with 422
        $resFail = $this->actingAs($this->cashier)->postJson('/pos/checkout', [
            'client_uuid' => (string) Str::uuid(),
            'store_id' => $this->store->id,
            'items' => [['product_id' => $this->product->id, 'quantity' => 1]],
            'paid_amount' => 6000.00,
            'payment_method' => 'bkash',
            'reference_no' => 'TRX-HIGH-VAL-01',
        ]);

        $resFail->assertStatus(422);
        $resFail->assertJsonValidationErrors(['payment']);

        // 2. Amount ৳6,000 with valid supervisor PIN succeeds
        $resSuccess = $this->actingAs($this->cashier)->postJson('/pos/checkout', [
            'client_uuid' => (string) Str::uuid(),
            'store_id' => $this->store->id,
            'items' => [['product_id' => $this->product->id, 'quantity' => 1]],
            'paid_amount' => 6000.00,
            'payment_method' => 'bkash',
            'reference_no' => 'TRX-HIGH-VAL-01',
            'supervisor_pin' => '8888', // Manager PIN
        ]);

        $resSuccess->assertStatus(200)->assertJson(['success' => true]);
    }

    /** @test */
    public function test_1d_cashier_exceeding_shift_counter_verified_count_requires_supervisor_pin(): void
    {
        $this->store->update([
            'max_counter_verified_amount' => 50000.00,
            'max_counter_verified_per_shift' => 2, // Set low limit for testing
        ]);

        // Sale 1: Succeeds without PIN
        $res1 = $this->actingAs($this->cashier)->postJson('/pos/checkout', [
            'client_uuid' => (string) Str::uuid(),
            'store_id' => $this->store->id,
            'items' => [['product_id' => $this->product->id, 'quantity' => 1]],
            'paid_amount' => 115.00,
            'payment_method' => 'bkash',
            'reference_no' => 'TRX-SHIFT-01',
        ]);
        $res1->assertStatus(200);

        // Sale 2: Succeeds without PIN (count now 2)
        $res2 = $this->actingAs($this->cashier)->postJson('/pos/checkout', [
            'client_uuid' => (string) Str::uuid(),
            'store_id' => $this->store->id,
            'items' => [['product_id' => $this->product->id, 'quantity' => 1]],
            'paid_amount' => 115.00,
            'payment_method' => 'bkash',
            'reference_no' => 'TRX-SHIFT-02',
        ]);
        $res2->assertStatus(200);

        // Sale 3: Exceeds shift threshold (2), fails without supervisor PIN
        $res3Fail = $this->actingAs($this->cashier)->postJson('/pos/checkout', [
            'client_uuid' => (string) Str::uuid(),
            'store_id' => $this->store->id,
            'items' => [['product_id' => $this->product->id, 'quantity' => 1]],
            'paid_amount' => 115.00,
            'payment_method' => 'bkash',
            'reference_no' => 'TRX-SHIFT-03',
        ]);
        $res3Fail->assertStatus(422);
        $res3Fail->assertJsonValidationErrors(['payment']);

        // Sale 3 with supervisor PIN succeeds
        $res3Success = $this->actingAs($this->cashier)->postJson('/pos/checkout', [
            'client_uuid' => (string) Str::uuid(),
            'store_id' => $this->store->id,
            'items' => [['product_id' => $this->product->id, 'quantity' => 1]],
            'paid_amount' => 115.00,
            'payment_method' => 'bkash',
            'reference_no' => 'TRX-SHIFT-03',
            'supervisor_pin' => '8888',
        ]);
        $res3Success->assertStatus(200)->assertJson(['success' => true]);
    }

    /** @test */
    public function test_1e_z_report_displays_counter_verified_count_and_total(): void
    {
        // Complete a counter-verified sale
        $this->actingAs($this->cashier)->postJson('/pos/checkout', [
            'client_uuid' => (string) Str::uuid(),
            'store_id' => $this->store->id,
            'items' => [['product_id' => $this->product->id, 'quantity' => 1]],
            'paid_amount' => 115.00,
            'payment_method' => 'bkash',
            'reference_no' => 'TRX-Z-REP-01',
        ]);

        $res = $this->actingAs($this->manager)->get("/manager/shifts/{$this->shift->id}/z-report");
        $res->assertStatus(200);

        $zData = $res->viewData('page')['props']['zData'];
        $this->assertEquals(1, $zData['counterVerifiedCount']);
        $this->assertEquals(115.00, (float) $zData['counterVerifiedTotal']);
    }

    /** @test */
    public function test_2_stock_policy_default_disallows_zero_stock_and_shelf_pickup_mode_records_real_negative_deficit(): void
    {
        $zeroProduct = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Zero Stock Product',
            'sku' => 'SKU-ZERO-POL-'.uniqid(),
            'selling_price' => 200.00,
            'is_active' => true,
        ]);

        $stock = Stock::create([
            'tenant_id' => $this->tenant->id,
            'store_id' => $this->store->id,
            'product_id' => $zeroProduct->id,
            'quantity' => 0.00,
        ]);

        // Default: shelf_pickup_mode = false, allow_negative_stock = false
        $this->store->update(['shelf_pickup_mode' => false, 'allow_negative_stock' => false]);

        $resFail = $this->actingAs($this->cashier)->postJson('/pos/checkout', [
            'client_uuid' => (string) Str::uuid(),
            'store_id' => $this->store->id,
            'items' => [['product_id' => $zeroProduct->id, 'quantity' => 3]],
            'paid_amount' => 690.00,
            'payment_method' => 'cash',
        ]);

        $resFail->assertStatus(422);
        $resFail->assertJsonValidationErrors(['cart']);

        // Enable shelf_pickup_mode
        $this->store->update(['shelf_pickup_mode' => true, 'allow_negative_stock' => false]);

        $resSuccess = $this->actingAs($this->cashier)->postJson('/pos/checkout', [
            'client_uuid' => (string) Str::uuid(),
            'store_id' => $this->store->id,
            'items' => [['product_id' => $zeroProduct->id, 'quantity' => 3]],
            'paid_amount' => 690.00,
            'payment_method' => 'cash',
        ]);

        $resSuccess->assertStatus(200)->assertJson(['success' => true]);

        // Real deficit recorded: 0.00 - 3 = -3.00 (Never clamped at 0)
        $stock->refresh();
        $this->assertEquals(-3.00, (float) $stock->quantity);

        // Order is flagged for review
        $order = Order::latest()->first();
        $this->assertTrue((bool) $order->flagged_for_review);
        $this->assertStringContainsString('Physical stock deficit', (string) $order->review_reason);
    }
}
