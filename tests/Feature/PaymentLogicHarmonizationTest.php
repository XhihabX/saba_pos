<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\DailySalesSummary;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderPayment;
use App\Models\Product;
use App\Models\ProductReturn;
use App\Models\RegisterShift;
use App\Models\SaaSPlan;
use App\Models\Store;
use App\Models\SubscriptionPayment;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\SaaSPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PaymentLogicHarmonizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_shift_close_and_z_report_include_real_mfs_and_card_tenders(): void
    {
        $this->seed(SaaSPlanSeeder::class);
        $plan = SaaSPlan::where('code', 'STARTER')->firstOrFail();

        $tenant = Tenant::create([
            'name' => 'Fashion Hub BD',
            'email' => 'fashion@hub.com',
            'code' => 'FASHION-01',
            'subscription_status' => 'active',
            'expires_at' => Carbon::now()->addDays(30),
            'mrr_amount' => $plan->monthly_price,
        ]);

        $store = Store::create([
            'tenant_id' => $tenant->id,
            'name' => 'Main Outlet',
            'code' => 'FASH-STORE-1',
            'is_active' => true,
        ]);

        $cashier = User::create([
            'tenant_id' => $tenant->id,
            'store_id' => $store->id,
            'name' => 'Cashier Karim',
            'email' => 'karim@fashionhub.com',
            'password' => Hash::make('Secret123!'),
            'role' => 'cashier',
            'pos_pin' => Hash::make('1234'),
        ]);

        // 1. Open shift with ৳1,000 opening float
        $shift = RegisterShift::create([
            'tenant_id' => $tenant->id,
            'store_id' => $store->id,
            'user_id' => $cashier->id,
            'opening_cash' => 1000.00,
            'status' => 'open',
            'opened_at' => Carbon::now()->subHours(2),
        ]);

        // 2. Create orders tendered via real Bangladeshi payment methods
        $order1 = Order::create([
            'tenant_id' => $tenant->id,
            'store_id' => $store->id,
            'user_id' => $cashier->id,
            'invoice_no' => 'INV-FASH-001',
            'subtotal' => 1500.00,
            'grand_total' => 1500.00,
            'paid_amount' => 1500.00,
            'change_return' => 0.00,
            'payment_method' => 'bkash',
            'payment_status' => 'paid',
        ]);
        OrderPayment::create([
            'tenant_id' => $tenant->id,
            'order_id' => $order1->id,
            'payment_method' => 'bkash',
            'amount' => 1500.00,
            'reference_no' => 'BKTRX998811',
            'verification_status' => 'counter_verified',
        ]);

        $order2 = Order::create([
            'tenant_id' => $tenant->id,
            'store_id' => $store->id,
            'user_id' => $cashier->id,
            'invoice_no' => 'INV-FASH-002',
            'subtotal' => 2000.00,
            'grand_total' => 2000.00,
            'paid_amount' => 2000.00,
            'change_return' => 0.00,
            'payment_method' => 'nagad',
            'payment_status' => 'paid',
        ]);
        OrderPayment::create([
            'tenant_id' => $tenant->id,
            'order_id' => $order2->id,
            'payment_method' => 'nagad',
            'amount' => 2000.00,
            'reference_no' => 'NGTRX776655',
            'verification_status' => 'counter_verified',
        ]);

        $order3 = Order::create([
            'tenant_id' => $tenant->id,
            'store_id' => $store->id,
            'user_id' => $cashier->id,
            'invoice_no' => 'INV-FASH-003',
            'subtotal' => 3500.00,
            'grand_total' => 3500.00,
            'paid_amount' => 3500.00,
            'change_return' => 0.00,
            'payment_method' => 'visa',
            'payment_status' => 'paid',
        ]);
        OrderPayment::create([
            'tenant_id' => $tenant->id,
            'order_id' => $order3->id,
            'payment_method' => 'visa',
            'amount' => 3500.00,
            'reference_no' => 'AUTH-443322',
            'verification_status' => 'not_applicable',
        ]);

        $order4 = Order::create([
            'tenant_id' => $tenant->id,
            'store_id' => $store->id,
            'user_id' => $cashier->id,
            'invoice_no' => 'INV-FASH-004',
            'subtotal' => 500.00,
            'grand_total' => 500.00,
            'paid_amount' => 500.00,
            'change_return' => 0.00,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
        ]);
        OrderPayment::create([
            'tenant_id' => $tenant->id,
            'order_id' => $order4->id,
            'payment_method' => 'cash',
            'amount' => 500.00,
            'verification_status' => 'not_applicable',
        ]);

        // 3. Close shift
        $this->actingAs($cashier);
        $resp = $this->post('/pos/shift/close', [
            'shift_id' => $shift->id,
            'closing_cash_counted' => 1500.00, // 1000 opening + 500 cash sales
            'notes' => 'Shift closed accurately',
        ]);
        $resp->assertStatus(302);

        $shift->refresh();
        $this->assertEquals('closed', $shift->status);
        $this->assertEquals(500.00, (float) $shift->total_cash_sales);
        // Both bKash (1500) and Nagad (2000) must be included in mobile sales!
        $this->assertEquals(3500.00, (float) $shift->total_mobile_sales);
        // Visa (3500) must be included in card sales!
        $this->assertEquals(3500.00, (float) $shift->total_card_sales);
        $this->assertEquals(1500.00, (float) $shift->expected_cash);
        $this->assertEquals(0.00, (float) $shift->cash_difference);

        // 4. Verify Z-Report displays the exact breakdowns
        $manager = User::create([
            'tenant_id' => $tenant->id,
            'store_id' => $store->id,
            'name' => 'Manager Rahim',
            'email' => 'rahim@fashionhub.com',
            'password' => Hash::make('Secret123!'),
            'role' => 'store_manager',
        ]);
        $this->actingAs($manager);
        $zResp = $this->get("/manager/shifts/{$shift->id}/z-report");
        $zResp->assertStatus(200);
        $zResp->assertInertia(fn ($page) => $page
            ->component('Manager/ZReport')
            ->where('zData.cashSales', 500)
            ->where('zData.mobileSales', 3500)
            ->where('zData.cardSales', 3500)
            ->where('zData.expectedCash', 1500)
        );
    }

    public function test_superadmin_approve_tenant_synchronizes_pending_subscription_payment(): void
    {
        $this->seed(SaaSPlanSeeder::class);
        $plan = SaaSPlan::where('code', 'GROWTH')->firstOrFail();

        $superAdmin = User::create([
            'name' => 'Platform Super Admin',
            'email' => 'admin_sync@sababilling.net',
            'password' => Hash::make('SuperSecret123!'),
            'role' => 'super_admin',
        ]);

        $tenant = Tenant::create([
            'name' => 'Delta Retailers',
            'email' => 'delta@retail.com',
            'code' => 'DELTA-01',
            'subscription_status' => 'pending_approval',
            'expires_at' => null,
            'transaction_id' => 'TXN-ONBOARD-9988',
            'mrr_amount' => $plan->monthly_price,
        ]);

        $payment = SubscriptionPayment::create([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'amount' => $plan->monthly_price,
            'method' => 'bank',
            'reference' => 'TXN-ONBOARD-9988',
            'status' => 'pending',
        ]);

        $this->actingAs($superAdmin);

        // Approve tenant onboarding application
        $resp = $this->post("/super-admin/tenants/{$tenant->id}/approve");
        $resp->assertStatus(302);
        $resp->assertSessionHas('success');

        $tenant->refresh();
        $payment->refresh();

        $this->assertEquals('active', $tenant->subscription_status);
        $this->assertNotNull($tenant->expires_at);
        $this->assertEquals(Carbon::now()->addDays(30)->toDateString(), Carbon::parse($tenant->expires_at)->toDateString());

        // Initial SubscriptionPayment must be automatically marked approved!
        $this->assertEquals('approved', $payment->status);
        $this->assertEquals($superAdmin->id, $payment->reviewed_by);
        $this->assertNotNull($payment->reviewed_at);

        // Subsequent attempt to approve the already approved payment must not double extend
        $resp2 = $this->post("/super-admin/subscription-payments/{$payment->id}/approve");
        $resp2->assertStatus(302);
        $resp2->assertSessionHas('info', 'This payment has already been approved.');

        $tenant->refresh();
        $this->assertEquals(Carbon::now()->addDays(30)->toDateString(), Carbon::parse($tenant->expires_at)->toDateString());
    }

    public function test_superadmin_reject_tenant_synchronizes_pending_subscription_payment(): void
    {
        $this->seed(SaaSPlanSeeder::class);
        $plan = SaaSPlan::where('code', 'STARTER')->firstOrFail();

        $superAdmin = User::create([
            'name' => 'Platform Super Admin',
            'email' => 'admin_reject_sync@sababilling.net',
            'password' => Hash::make('SuperSecret123!'),
            'role' => 'super_admin',
        ]);

        $tenant = Tenant::create([
            'name' => 'Spam Retailers',
            'email' => 'spam@retail.com',
            'code' => 'SPAM-01',
            'subscription_status' => 'pending_approval',
            'transaction_id' => 'TXN-FAKE-1122',
            'mrr_amount' => $plan->monthly_price,
        ]);

        $payment = SubscriptionPayment::create([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'amount' => $plan->monthly_price,
            'method' => 'bkash',
            'reference' => 'TXN-FAKE-1122',
            'status' => 'pending',
        ]);

        $this->actingAs($superAdmin);

        $resp = $this->post("/super-admin/tenants/{$tenant->id}/reject");
        $resp->assertStatus(302);
        $resp->assertSessionHas('success');

        $tenant->refresh();
        $payment->refresh();

        $this->assertEquals('rejected', $tenant->subscription_status);
        $this->assertEquals('rejected', $payment->status);
        $this->assertEquals($superAdmin->id, $payment->reviewed_by);
    }

    public function test_due_return_does_not_deduct_from_shift_cash_drawer(): void
    {
        $tenant = Tenant::create([
            'name' => 'Metro Supermarket',
            'email' => 'metro@supermarket.com',
            'code' => 'METRO-01',
            'subscription_status' => 'active',
            'expires_at' => Carbon::now()->addDays(30),
        ]);

        $store = Store::create([
            'tenant_id' => $tenant->id,
            'name' => 'Dhanmondi Branch',
            'code' => 'METRO-DHN',
            'is_active' => true,
        ]);

        $cashier = User::create([
            'tenant_id' => $tenant->id,
            'store_id' => $store->id,
            'name' => 'Cashier Tanvir',
            'email' => 'tanvir@metro.com',
            'password' => Hash::make('Secret123!'),
            'role' => 'cashier',
            'pos_pin' => Hash::make('5566'),
        ]);

        $customer = Customer::create([
            'tenant_id' => $tenant->id,
            'name' => 'Credit Customer Rahim',
            'phone' => '01711000000',
            'due_balance' => 800.00,
        ]);

        $product = Product::create([
            'tenant_id' => $tenant->id,
            'name' => 'Rice Bag 5KG',
            'sku' => 'RICE-5KG',
            'selling_price' => 400.00,
            'cost_price' => 320.00,
        ]);

        $shift = RegisterShift::create([
            'tenant_id' => $tenant->id,
            'store_id' => $store->id,
            'user_id' => $cashier->id,
            'opening_cash' => 2000.00,
            'status' => 'open',
            'opened_at' => Carbon::now()->subHour(),
        ]);

        // Unpaid / due order
        $order = Order::create([
            'tenant_id' => $tenant->id,
            'store_id' => $store->id,
            'user_id' => $cashier->id,
            'customer_id' => $customer->id,
            'invoice_no' => 'INV-METRO-DUE-01',
            'subtotal' => 400.00,
            'grand_total' => 400.00,
            'paid_amount' => 0.00,
            'change_return' => 0.00,
            'payment_method' => 'due',
            'payment_status' => 'due',
        ]);
        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => 1,
            'unit_price' => 400.00,
            'total' => 400.00,
        ]);

        // Return the item from the unpaid order with supervisor PIN
        $this->actingAs($cashier);
        $returnResp = $this->postJson('/pos/returns', [
            'invoice_no' => $order->invoice_no,
            'product_id' => $product->id,
            'quantity' => 1,
            'refund_amount' => 400.00,
            'reason' => 'Damaged bag',
            'supervisor_pin' => '5566',
        ]);
        $returnResp->assertStatus(200);

        // Verify return record was tagged as due_adjustment
        $ret = ProductReturn::where('order_id', $order->id)->firstOrFail();
        $this->assertEquals('due_adjustment', $ret->refund_method);

        // Close shift: drawer expected cash must remain ৳2000 (opening cash), NOT reduced by 400!
        $this->post('/pos/shift/close', [
            'shift_id' => $shift->id,
            'closing_cash_counted' => 2000.00,
        ]);

        $shift->refresh();
        $this->assertEquals(2000.00, (float) $shift->expected_cash);
        $this->assertEquals(0.00, (float) $shift->cash_difference);
    }

    public function test_merchant_pay_customer_due_records_audit_log_and_syncs_with_cash_drawer(): void
    {
        $tenant = Tenant::create([
            'name' => 'Bismillah Store',
            'email' => 'bismillah@store.com',
            'code' => 'BISM-01',
            'subscription_status' => 'active',
            'expires_at' => Carbon::now()->addDays(30),
        ]);

        $store = Store::create([
            'tenant_id' => $tenant->id,
            'name' => 'Counter Store',
            'code' => 'BISM-STR-1',
            'is_active' => true,
        ]);

        $merchant = User::create([
            'tenant_id' => $tenant->id,
            'store_id' => $store->id,
            'name' => 'Haji Saheb',
            'email' => 'owner@bismillah.com',
            'password' => Hash::make('Secret123!'),
            'role' => 'merchant',
        ]);

        $customer = Customer::create([
            'tenant_id' => $tenant->id,
            'name' => 'Babul Mia',
            'phone' => '01888000000',
            'due_balance' => 1000.00,
        ]);

        $shift = RegisterShift::create([
            'tenant_id' => $tenant->id,
            'store_id' => $store->id,
            'user_id' => $merchant->id,
            'opening_cash' => 500.00,
            'status' => 'open',
            'opened_at' => Carbon::now()->subHours(2),
        ]);

        $this->actingAs($merchant);

        // 1. Collect ৳300 due payment in CASH
        $resp = $this->post("/merchant/customers/{$customer->id}/pay-due", [
            'amount' => 300.00,
            'payment_method' => 'cash',
            'notes' => 'Paid at counter',
        ]);
        $resp->assertStatus(302);

        $customer->refresh();
        $this->assertEquals(700.00, (float) $customer->due_balance);

        // Verify AuditLog was recorded
        $log = AuditLog::where('tenant_id', $tenant->id)->where('action', 'customer_due_paid')->firstOrFail();
        $this->assertEquals(300.00, (float) $log->payload['amount']);
        $this->assertEquals('cash', $log->payload['payment_method']);

        // 2. Collect ৳200 due payment via BKASH
        $respBkash = $this->post("/merchant/customers/{$customer->id}/pay-due", [
            'amount' => 200.00,
            'payment_method' => 'bkash',
            'notes' => 'bKash personal transfer',
        ]);
        $respBkash->assertStatus(302);

        $customer->refresh();
        $this->assertEquals(500.00, (float) $customer->due_balance);

        // 3. Close shift: expected cash = 500 (opening) + 300 (cash due) = ৳800. bKash must NOT be added to cash drawer!
        $this->post('/pos/shift/close', [
            'shift_id' => $shift->id,
            'closing_cash_counted' => 800.00,
        ]);

        $shift->refresh();
        $this->assertEquals(800.00, (float) $shift->expected_cash);
        $this->assertEquals(0.00, (float) $shift->cash_difference);
    }

    public function test_daily_sales_summary_normalizes_card_and_mfs_aliases(): void
    {
        $buckets = DailySalesSummary::normalizePaymentBuckets([
            'credit_card' => 1200.00,
            'visa' => 800.00,
            'pos_terminal' => 500.00,
            'mobile_wallet' => 700.00,
            'bkash' => 1500.00,
            'nagad' => 900.00,
            'cash' => 2000.00,
            'due' => 450.00,
        ]);

        // Card aliases (1200 + 800 + 500 = 2500)
        $this->assertEquals(2500.00, $buckets['card_total']);
        // Cash
        $this->assertEquals(2000.00, $buckets['cash_total']);
        // Due
        $this->assertEquals(450.00, $buckets['due_total']);
        // Nagad
        $this->assertEquals(900.00, $buckets['nagad_total']);
        // bKash + generic mobile_wallet (1500 + 700 = 2200)
        $this->assertEquals(2200.00, $buckets['bkash_total']);
        // other_total should be 0 because all were mapped accurately!
        $this->assertEquals(0.00, $buckets['other_total']);
    }
}
