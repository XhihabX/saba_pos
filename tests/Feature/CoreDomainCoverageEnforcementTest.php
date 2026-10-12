<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureActiveSubscription;
use App\Http\Middleware\EnsureMerchant;
use App\Http\Middleware\EnsureStoreManager;
use App\Http\Middleware\EnsureSuperAdmin;
use App\Models\AuditLog;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Customer;
use App\Models\DailySalesSummary;
use App\Models\Expense;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderPayment;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\ProductReturn;
use App\Models\RegisterShift;
use App\Models\SaaSPlan;
use App\Models\Stock;
use App\Models\StockAdjustment;
use App\Models\StockAudit;
use App\Models\StockAuditItem;
use App\Models\StockTransfer;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Services\EfdBridgeService;
use App\Services\TwoFactorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Tests\TestCase;

class CoreDomainCoverageEnforcementTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected Store $storeA;
    protected Store $storeB;
    protected User $merchant;
    protected User $manager;
    protected User $cashier;
    protected User $superAdmin;
    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.mfs.secret_key', 'test_mfs_secret_999');
        Config::set('services.efd.secret_key', 'test_efd_secret_888');

        $this->tenant = Tenant::create([
            'name' => 'Coverage Test Tenant',
            'code' => 'COV-TEN-01',
            'email' => 'coverage@iotpos.com',
            'status' => 'active',
            'subscription_status' => 'active',
            'expires_at' => now()->addMonth(),
        ]);

        $this->storeA = Store::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Coverage Store A',
            'code' => 'STORE-COV-A',
            'currency_symbol' => '৳',
            'bin_number' => '123456789-0101',
            'is_vat_registered' => true,
            'default_tax_rate' => 15.0,
            'allow_negative_stock' => false,
            'is_active' => true,
        ]);

        $this->storeB = Store::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Coverage Store B',
            'code' => 'STORE-COV-B',
            'currency_symbol' => '৳',
            'bin_number' => '987654321-0202',
            'is_vat_registered' => true,
            'default_tax_rate' => 15.0,
            'allow_negative_stock' => false,
            'is_active' => true,
        ]);

        $this->superAdmin = User::create([
            'name' => 'Super Admin Coverage',
            'email' => 'admin@iotpos.com',
            'password' => Hash::make('password123'),
            'role' => 'super_admin',
            'is_approved' => true,
        ]);

        $this->merchant = User::create([
            'tenant_id' => $this->tenant->id,
            'store_id' => $this->storeA->id,
            'name' => 'Merchant Coverage',
            'email' => 'merchant@iotpos.com',
            'password' => Hash::make('password123'),
            'pos_pin' => Hash::make('1234'),
            'role' => 'merchant',
            'is_approved' => true,
        ]);

        $this->manager = User::create([
            'tenant_id' => $this->tenant->id,
            'store_id' => $this->storeA->id,
            'name' => 'Manager Coverage',
            'email' => 'manager@iotpos.com',
            'password' => Hash::make('password123'),
            'pos_pin' => Hash::make('2222'),
            'role' => 'store_manager',
            'is_approved' => true,
        ]);

        $this->cashier = User::create([
            'tenant_id' => $this->tenant->id,
            'store_id' => $this->storeA->id,
            'name' => 'Cashier Coverage',
            'email' => 'cashier@iotpos.com',
            'password' => Hash::make('password123'),
            'pos_pin' => Hash::make('3333'),
            'role' => 'cashier',
            'is_approved' => true,
        ]);

        $this->product = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Coverage Test Item',
            'sku' => 'COV-SKU-100',
            'purchase_cost' => 50.00,
            'selling_price' => 100.00,
            'vat_rate' => 15.0,
            'is_active' => true,
        ]);

        Stock::create([
            'tenant_id' => $this->tenant->id,
            'store_id' => $this->storeA->id,
            'product_id' => $this->product->id,
            'quantity' => 100,
        ]);

        Stock::create([
            'tenant_id' => $this->tenant->id,
            'store_id' => $this->storeB->id,
            'product_id' => $this->product->id,
            'quantity' => 20,
        ]);
    }

    // =========================================================================
    // 1. AUTH CONTROLLER TESTS
    // =========================================================================

    public function test_auth_controller_login_redirections_and_failures(): void
    {
        // 1. Show login view
        $res = $this->get('/login');
        $res->assertStatus(200);

        // 2. Failed login
        $resFail = $this->post('/login', [
            'email' => 'merchant@iotpos.com',
            'password' => 'wrong_password',
        ]);
        $resFail->assertSessionHasErrors('email');

        // 3. Super admin login redirect
        $resAdmin = $this->post('/login', [
            'email' => 'admin@iotpos.com',
            'password' => 'password123',
        ]);
        $resAdmin->assertRedirect('/super-admin/dashboard');
        Auth::logout();

        // 4. Merchant login redirect
        $resMerchant = $this->post('/login', [
            'email' => 'merchant@iotpos.com',
            'password' => 'password123',
        ]);
        $resMerchant->assertRedirect('/merchant/dashboard');
        Auth::logout();

        // 5. Store manager login redirect
        $resManager = $this->post('/login', [
            'email' => 'manager@iotpos.com',
            'password' => 'password123',
        ]);
        $resManager->assertRedirect('/manager/dashboard');
        Auth::logout();

        // 6. Cashier login redirect
        $resCashier = $this->post('/login', [
            'email' => 'cashier@iotpos.com',
            'password' => 'password123',
        ]);
        $resCashier->assertRedirect('/pos');
        Auth::logout();
    }

    public function test_auth_controller_switch_role_and_logout(): void
    {
        $controller = new \App\Http\Controllers\AuthController();
        $this->actingAs($this->merchant);

        $request = Request::create('/switch-role', 'GET');
        $session = app('session.store');
        $session->start();
        $request->setLaravelSession($session);
        app()->instance('request', $request);

        // Switch to super_admin
        $resAdmin = $controller->switchRole('super_admin');
        $this->assertEquals(302, $resAdmin->getStatusCode());
        $this->assertEquals(url('/super-admin/dashboard'), $resAdmin->getTargetUrl());

        // Switch to merchant
        $resMerchant = $controller->switchRole('merchant');
        $this->assertEquals(302, $resMerchant->getStatusCode());
        $this->assertEquals(url('/merchant/dashboard'), $resMerchant->getTargetUrl());

        // Switch to store_manager
        $resManager = $controller->switchRole('store_manager');
        $this->assertEquals(302, $resManager->getStatusCode());
        $this->assertEquals(url('/manager/dashboard'), $resManager->getTargetUrl());

        // Switch to cashier
        $resCashier = $controller->switchRole('cashier');
        $this->assertEquals(302, $resCashier->getStatusCode());
        $this->assertEquals(url('/pos'), $resCashier->getTargetUrl());

        // Switch to unknown role fallback
        $resUnknown = $controller->switchRole('unknown_role');
        $this->assertEquals(302, $resUnknown->getStatusCode());
        $this->assertEquals(url('/merchant/dashboard'), $resUnknown->getTargetUrl());

        // Logout
        $resLogout = $this->post('/logout');
        $resLogout->assertRedirect('/login');
        $this->assertFalse(Auth::check());
    }

    public function test_auth_controller_registration_flow_and_views(): void
    {
        SaaSPlan::create([
            'name' => 'Growth Multi-Store',
            'code' => 'GROWTH',
            'monthly_price' => 3999.00,
            'is_active' => true,
        ]);

        $this->get('/register')->assertStatus(200);
        $this->actingAs($this->merchant)->get('/pending-approval')->assertStatus(200);
        $this->actingAs($this->merchant)->get('/subscription-expired')->assertStatus(200);
        Auth::logout();

        $regPayload = [
            'business_name' => 'New Enterprise Chain',
            'owner_name' => 'Farhan Ahmed',
            'email' => 'farhan@enterprise.local',
            'phone' => '01711000999',
            'password' => 'secret12345',
            'password_confirmation' => 'secret12345',
            'plan_name' => 'Growth Multi-Store',
            'payment_method' => 'bkash',
            'sender_number' => '01711000999',
            'transaction_id' => 'TRXREG123456',
        ];

        $resReg = $this->post('/register', $regPayload);
        $resReg->assertRedirect(route('pending.approval'));

        $this->assertDatabaseHas('tenants', [
            'name' => 'New Enterprise Chain',
            'email' => 'farhan@enterprise.local',
            'subscription_status' => 'pending_approval',
            'transaction_id' => 'TRXREG123456',
        ]);

        $this->assertDatabaseHas('users', [
            'email' => 'farhan@enterprise.local',
            'role' => 'merchant',
        ]);
    }

    // =========================================================================
    // 2. TWO FACTOR CONTROLLER & SERVICE TESTS
    // =========================================================================

    public function test_two_factor_controller_and_service_complete_lifecycle(): void
    {
        $service = new TwoFactorService();
        $secret = $service->generateSecretKey();
        $this->assertEquals(16, strlen($secret));

        // Cashier forbidden from 2FA setup
        $res403 = $this->actingAs($this->cashier)->getJson('/2fa/setup');
        $res403->assertStatus(403);

        // Merchant setup
        $resSetup = $this->actingAs($this->merchant)->getJson('/2fa/setup');
        $resSetup->assertStatus(200)->assertJsonStructure(['secret', 'qr_code_url', 'is_confirmed']);
        $generatedSecret = $resSetup->json('secret');

        // Invalid code enable fails
        $resBadEnable = $this->postJson('/2fa/enable', ['code' => '999999']);
        $resBadEnable->assertStatus(422);

        // Calculate valid OTP via reflection or time slice
        $timeSlice = (int) floor(time() / 30);
        $reflection = new \ReflectionClass(TwoFactorService::class);
        $calcMethod = $reflection->getMethod('calculateOtp');
        $calcMethod->setAccessible(true);
        $validOtp = $calcMethod->invoke($service, $generatedSecret, $timeSlice);

        // Enable 2FA with valid OTP
        $resEnable = $this->postJson('/2fa/enable', ['code' => $validOtp]);
        $resEnable->assertStatus(200)->assertJsonStructure(['success', 'recovery_codes']);
        $recoveryCodes = $resEnable->json('recovery_codes');
        $this->assertCount(8, $recoveryCodes);

        $this->merchant->refresh();
        $this->assertNotNull($this->merchant->two_factor_confirmed_at);

        // Challenge page
        $this->get('/2fa/challenge')->assertStatus(200);

        // Verify with invalid code fails
        $resBadVerify = $this->post('/2fa/verify', ['code' => '000000']);
        $resBadVerify->assertSessionHasErrors('code');

        // Verify with valid recovery code
        $firstRecoveryCode = $recoveryCodes[0];
        $resRecoveryVerify = $this->post('/2fa/verify', ['code' => $firstRecoveryCode]);
        $resRecoveryVerify->assertRedirect('/dashboard');
        $this->assertTrue(session('2fa:verified'));

        // Verify recovery code was consumed
        $this->merchant->refresh();
        $remainingCodes = json_decode(decrypt($this->merchant->two_factor_recovery_codes), true);
        $this->assertNotContains(strtoupper($firstRecoveryCode), $remainingCodes);
    }

    // =========================================================================
    // 3. PRODUCT RETURN CONTROLLER DEEP VALIDATIONS & RESTOCKING
    // =========================================================================

    public function test_return_controller_validations_and_restocking_branches(): void
    {
        $customer = Customer::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Return Loyalty Customer',
            'phone' => '01712999888',
            'points' => 50,
            'due_balance' => 500.00,
        ]);

        $order = Order::create([
            'tenant_id' => $this->tenant->id,
            'store_id' => $this->storeA->id,
            'user_id' => $this->cashier->id,
            'customer_id' => $customer->id,
            'invoice_no' => 'INV-RET-TEST-01',
            'subtotal' => 200.00,
            'tax_amount' => 30.00,
            'grand_total' => 230.00,
            'paid_amount' => 230.00,
            'payment_status' => 'paid',
            'payment_method' => 'cash',
        ]);

        $orderItem = OrderItem::create([
            'tenant_id' => $this->tenant->id,
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'product_name' => $this->product->name,
            'quantity' => 2,
            'unit_price' => 100.00,
            'cost_price' => 50.00,
            'vat_rate' => 15.0,
            'vat_amount' => 30.00,
            'vat_mode' => 'exclusive',
            'total' => 200.00,
        ]);

        $batch = ProductBatch::create([
            'tenant_id' => $this->tenant->id,
            'store_id' => $this->storeA->id,
            'product_id' => $this->product->id,
            'batch_no' => 'BATCH-RET-01',
            'quantity' => 10,
            'expiry_date' => now()->addDays(90),
        ]);

        $shift = RegisterShift::create([
            'tenant_id' => $this->tenant->id,
            'store_id' => $this->storeA->id,
            'user_id' => $this->cashier->id,
            'opening_cash' => 500.00,
            'status' => 'open',
            'opened_at' => now(),
            'total_refunds' => 0.00,
        ]);

        // Manager returns view
        $this->actingAs($this->manager)->get('/manager/returns')->assertStatus(200);

        // 1. Validation failure: Product not in order
        $otherProduct = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Unrelated Item',
            'sku' => 'UNRELATED-001',
            'purchase_cost' => 10.00,
            'selling_price' => 20.00,
        ]);

        $resBadProd = $this->actingAs($this->manager)->post('/manager/returns', [
            'invoice_no' => $order->invoice_no,
            'product_id' => $otherProduct->id,
            'quantity' => 1,
            'refund_amount' => 100.00,
            'reason' => 'Wrong product return',
        ]);
        $resBadProd->assertSessionHasErrors('product_id');

        // 2. Validation failure: Quantity exceeds order
        $resBadQty = $this->actingAs($this->manager)->post('/manager/returns', [
            'invoice_no' => $order->invoice_no,
            'product_id' => $this->product->id,
            'quantity' => 5,
            'refund_amount' => 100.00,
            'reason' => 'Too many items',
        ]);
        $resBadQty->assertSessionHasErrors('quantity');

        // 3. Validation failure: Refund amount exceeds max refundable
        $resBadAmount = $this->actingAs($this->manager)->post('/manager/returns', [
            'invoice_no' => $order->invoice_no,
            'product_id' => $this->product->id,
            'quantity' => 1,
            'refund_amount' => 200.00, // Line paid price is (200 + 30)/2 = 115
            'reason' => 'Too high refund',
        ]);
        $resBadAmount->assertSessionHasErrors('refund_amount');

        // 4. Successful return: restores stock, updates batch, updates customer, updates shift refunds
        $resGood = $this->actingAs($this->manager)->post('/manager/returns', [
            'invoice_no' => $order->invoice_no,
            'product_id' => $this->product->id,
            'quantity' => 1,
            'refund_amount' => 115.00,
            'reason' => 'Customer changed mind',
        ]);
        $resGood->assertStatus(302);

        // Verify stock incremented by 1 (100 -> 101)
        $this->assertDatabaseHas('stocks', [
            'store_id' => $this->storeA->id,
            'product_id' => $this->product->id,
            'quantity' => 101,
        ]);

        // Verify batch incremented (10 -> 11)
        $this->assertEquals(11, $batch->fresh()->quantity);

        // Verify ProductReturn recorded
        $this->assertEquals(115.00, (float) ProductReturn::where('order_id', $order->id)->sum('refund_amount'));

        // Verify customer points deducted (115/100 = 1 point)
        $this->assertEquals(49, $customer->fresh()->points);
    }

    // =========================================================================
    // 4. REGISTER SHIFT LIFECYCLE & DAY-END Z-REPORT
    // =========================================================================

    public function test_shift_controller_complete_lifecycle(): void
    {
        $this->actingAs($this->cashier);

        // 1. Shift status when no shift open
        $resStatus1 = $this->getJson('/pos/shift/status?store_id=' . $this->storeA->id);
        $resStatus1->assertStatus(200)->assertJson(['isOpen' => false]);

        // 2. Open shift with invalid store
        $resBadStore = $this->postJson('/pos/shift/open', [
            'store_id' => 9999,
            'opening_cash' => 500.00,
        ]);
        $resBadStore->assertStatus(422);

        // 3. Open shift successfully
        $resOpen = $this->postJson('/pos/shift/open', [
            'store_id' => $this->storeA->id,
            'opening_cash' => 1000.00,
            'notes' => 'Morning float',
        ]);
        $resOpen->assertStatus(200)->assertJson(['success' => true]);
        $shiftId = $resOpen->json('shift.id');

        // Check status now open
        $resStatus2 = $this->getJson('/pos/shift/status?store_id=' . $this->storeA->id);
        $resStatus2->assertStatus(200)->assertJson(['isOpen' => true]);

        // 4. Create an order with cash, card, mobile wallet payments during shift
        $order = Order::create([
            'tenant_id' => $this->tenant->id,
            'store_id' => $this->storeA->id,
            'user_id' => $this->cashier->id,
            'invoice_no' => 'INV-SHIFT-T1',
            'subtotal' => 600.00,
            'tax_amount' => 90.00,
            'grand_total' => 690.00,
            'paid_amount' => 700.00,
            'change_return' => 10.00,
            'payment_status' => 'paid',
            'payment_method' => 'split',
        ]);

        OrderPayment::create([
            'order_id' => $order->id,
            'payment_method' => 'cash',
            'amount' => 300.00,
        ]);

        OrderPayment::create([
            'order_id' => $order->id,
            'payment_method' => 'card',
            'amount' => 200.00,
        ]);

        OrderPayment::create([
            'order_id' => $order->id,
            'payment_method' => 'mobile_wallet',
            'amount' => 200.00,
        ]);

        // 5. Close shift with invalid shift ID
        $resBadClose = $this->postJson('/pos/shift/close', [
            'shift_id' => 88888,
            'closing_cash_counted' => 1290.00,
        ]);
        $resBadClose->assertStatus(422);

        // 6. Close shift successfully with cash count
        // Expected cash: 1000 (opening) + 300 (cash sales) - 10 (change return) = 1290.00
        $resClose = $this->postJson('/pos/shift/close', [
            'shift_id' => $shiftId,
            'closing_cash_counted' => 1300.00, // 10 overage
            'notes' => '৳10 tip left in drawer',
        ]);
        $resClose->assertStatus(200)->assertJson(['success' => true]);

        $closedShift = RegisterShift::find($shiftId);
        $this->assertEquals('closed', $closedShift->status);
        $this->assertEquals(1290.00, (float) $closedShift->expected_cash);
        $this->assertEquals(1300.00, (float) $closedShift->closing_cash_counted);
        $this->assertEquals(10.00, (float) $closedShift->cash_difference);

        // 7. View Z-Report
        $resZ = $this->actingAs($this->manager)->get("/manager/shifts/{$shiftId}/z-report");
        $resZ->assertStatus(200);
    }

    // =========================================================================
    // 5. STOCK ADJUSTMENT, AUDIT & TRANSFER CONTROLLERS
    // =========================================================================

    public function test_stock_adjustments_audits_and_transfers(): void
    {
        $this->actingAs($this->manager);

        // 1. Stock Adjustment View & Actions
        $this->get('/inventory/adjustments')->assertStatus(200);

        // Addition
        $resAdd = $this->post('/inventory/adjustments', [
            'store_id' => $this->storeA->id,
            'product_id' => $this->product->id,
            'type' => 'audit_addition',
            'quantity' => 25,
            'notes' => 'Stock found in storage',
        ]);
        $resAdd->assertStatus(302);
        $this->assertEquals(125, Stock::where('store_id', $this->storeA->id)->where('product_id', $this->product->id)->value('quantity'));

        // Deduction / Damage
        $resDed = $this->post('/inventory/adjustments', [
            'store_id' => $this->storeA->id,
            'product_id' => $this->product->id,
            'type' => 'damage',
            'quantity' => 5,
            'notes' => 'Water damage',
        ]);
        $resDed->assertStatus(302);
        $this->assertEquals(120, Stock::where('store_id', $this->storeA->id)->where('product_id', $this->product->id)->value('quantity'));

        // 2. Stock Transfer View & Action
        $this->get('/manager/transfers')->assertStatus(200);

        // Transfer failure: Insufficient stock
        $resBadTrf = $this->post('/manager/transfers', [
            'from_store_id' => $this->storeA->id,
            'to_store_id' => $this->storeB->id,
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 9999],
            ],
        ]);
        $resBadTrf->assertSessionHasErrors('items');

        // Valid transfer: 20 units from Store A (120 -> 100) to Store B (20 -> 40)
        $resGoodTrf = $this->post('/manager/transfers', [
            'from_store_id' => $this->storeA->id,
            'to_store_id' => $this->storeB->id,
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 20],
            ],
            'notes' => 'Transfer 20 units',
        ]);
        $resGoodTrf->assertStatus(302);
        $this->assertEquals(100, Stock::where('store_id', $this->storeA->id)->where('product_id', $this->product->id)->value('quantity'));
        $this->assertEquals(40, Stock::where('store_id', $this->storeB->id)->where('product_id', $this->product->id)->value('quantity'));

        // 3. Stock Audit Controller
        $resAuditIndex = $this->getJson('/stock-audits');
        $resAuditIndex->assertStatus(200);

        // Start stock audit
        $resAuditStart = $this->postJson('/stock-audits', [
            'store_id' => $this->storeA->id,
            'notes' => 'Monthly branch audit',
        ]);
        $resAuditStart->assertStatus(201);
        $auditId = $resAuditStart->json('audit.id');
        $auditItemId = $resAuditStart->json('audit.items.0.id');

        // Update counted quantities
        $resUpdateItems = $this->postJson("/stock-audits/{$auditId}/items", [
            'items' => [
                ['id' => $auditItemId, 'counted_qty' => 95], // 5 unit variance
            ],
        ]);
        $resUpdateItems->assertStatus(200);

        // Approve audit
        $resApprove = $this->postJson("/stock-audits/{$auditId}/approve");
        $resApprove->assertStatus(200);
        $this->assertEquals(95, Stock::where('store_id', $this->storeA->id)->where('product_id', $this->product->id)->value('quantity'));

        // Second approve fails
        $resApproveAgain = $this->postJson("/stock-audits/{$auditId}/approve");
        $resApproveAgain->assertStatus(422);
    }

    // =========================================================================
    // 6. EXPENSE CONTROLLER CRUD TESTS
    // =========================================================================

    public function test_expense_controller_crud_operations(): void
    {
        $this->actingAs($this->manager);

        // Index
        $this->get('/expenses')->assertStatus(200);

        // Create
        $resStore = $this->post('/expenses', [
            'title' => 'Office Wi-Fi Internet',
            'category' => 'utilities',
            'amount' => 1500.00,
            'date' => now()->toDateString(),
            'notes' => 'Fiber connection bill',
        ]);
        $resStore->assertStatus(302);

        $expense = Expense::where('title', 'Office Wi-Fi Internet')->first();
        $this->assertNotNull($expense);

        // Update
        $resUpdate = $this->post("/expenses/{$expense->id}/update", [
            'title' => 'Office Wi-Fi Internet Paid',
            'category' => 'utilities',
            'amount' => 1600.00,
            'date' => now()->toDateString(),
            'notes' => 'Updated amount',
        ]);
        $resUpdate->assertStatus(302);
        $this->assertEquals(1600.00, (float) $expense->fresh()->amount);

        // Delete
        $resDelete = $this->delete("/expenses/{$expense->id}");
        $resDelete->assertStatus(302);
        $this->assertNull(Expense::find($expense->id));
    }

    // =========================================================================
    // 7. PRODUCT CONTROLLER CRUD, CATEGORIES, BRANDS & UNITS
    // =========================================================================

    public function test_product_controller_crud_and_metadata(): void
    {
        $this->actingAs($this->merchant);

        // Index
        $this->get('/products')->assertStatus(200);

        // Store Category
        $resCat = $this->post('/products/categories', ['name' => 'Peripherals']);
        $resCat->assertStatus(302);
        $cat = Category::where('name', 'Peripherals')->first();
        $this->assertNotNull($cat);

        // Store Brand
        $resBrand = $this->post('/products/brands', ['name' => 'Logitech']);
        $resBrand->assertStatus(302);
        $brand = Brand::where('name', 'Logitech')->first();
        $this->assertNotNull($brand);

        // Store Unit
        $resUnit = $this->post('/products/units', ['name' => 'Pieces', 'short_name' => 'pcs']);
        $resUnit->assertStatus(302);
        $unit = Unit::where('short_name', 'pcs')->first();
        $this->assertNotNull($unit);

        // Create Product
        $resProd = $this->post('/products', [
            'name' => 'Wireless Mouse M185',
            'sku' => 'LOGI-M185',
            'barcode' => '8901234567890',
            'category_id' => $cat->id,
            'brand_id' => $brand->id,
            'unit_id' => $unit->id,
            'purchase_cost' => 800.00,
            'selling_price' => 1200.00,
            'initial_stock' => 50,
        ]);
        $resProd->assertStatus(302);
        $prod = Product::where('sku', 'LOGI-M185')->first();
        $this->assertNotNull($prod);

        // Verify initial stock created in stores
        $this->assertEquals(50, Stock::where('store_id', $this->storeA->id)->where('product_id', $prod->id)->value('quantity'));

        // Update Product
        $resUpdate = $this->post("/products/{$prod->id}/update", [
            'name' => 'Wireless Mouse M185 Gray',
            'sku' => 'LOGI-M185',
            'purchase_cost' => 850.00,
            'selling_price' => 1250.00,
        ]);
        $resUpdate->assertStatus(302);
        $this->assertEquals(1250.00, (float) $prod->fresh()->selling_price);

        // Delete Product
        $resDel = $this->delete("/products/{$prod->id}");
        $resDel->assertStatus(302);
        $this->assertSoftDeleted('products', ['id' => $prod->id]);
    }

    // =========================================================================
    // 8. EFD BRIDGE SERVICE STATUTORY PAYLOAD GENERATION
    // =========================================================================

    public function test_efd_bridge_service_payload_and_validation(): void
    {
        $order = Order::create([
            'tenant_id' => $this->tenant->id,
            'store_id' => $this->storeA->id,
            'user_id' => $this->cashier->id,
            'invoice_no' => 'INV-EFD-001',
            'subtotal' => 500.00,
            'discount_amount' => 50.00,
            'tax_amount' => 67.50,
            'grand_total' => 517.50,
            'paid_amount' => 517.50,
            'payment_method' => 'cash',
        ]);

        OrderItem::create([
            'tenant_id' => $this->tenant->id,
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'product_name' => $this->product->name,
            'quantity' => 5,
            'unit_price' => 100.00,
            'vat_rate' => 15.0,
            'vat_amount' => 67.50,
            'total' => 500.00,
        ]);

        $payload = EfdBridgeService::generatePayload($order);

        $this->assertIsArray($payload);
        $this->assertEquals('123456789-0101', $payload['bin']);
        $this->assertEquals('INV-EFD-001', $payload['invoice_number']);
        $this->assertEquals('READY_FOR_SDC', $payload['efd_status']);
        $this->assertNotEmpty($payload['security_hash']);
        $this->assertCount(1, $payload['items']);

        // Test exception if store is VAT registered but lacks BIN
        $storeNoBin = Store::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'No BIN Store',
            'code' => 'STORE-NO-BIN',
            'is_vat_registered' => true,
            'bin_number' => null,
            'vat_number' => null,
            'is_active' => true,
        ]);

        $orderNoBin = Order::create([
            'tenant_id' => $this->tenant->id,
            'store_id' => $storeNoBin->id,
            'user_id' => $this->cashier->id,
            'invoice_no' => 'INV-NO-BIN-001',
            'subtotal' => 100.00,
            'grand_total' => 115.00,
            'paid_amount' => 115.00,
            'payment_method' => 'cash',
        ]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        EfdBridgeService::generatePayload($orderNoBin);
    }
}
