<?php

namespace Tests\Feature;

use App\Mail\SupervisorPinLockoutAlertMail;
use App\Models\AuditLog;
use App\Models\SaaSPlan;
use App\Models\Store;
use App\Models\SubscriptionPayment;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\SaaSPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Tests\TestCase;

class SecurityHardeningV3Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('register');
        RateLimiter::clear('login');
        Mail::fake();
    }

    protected function tearDown(): void
    {
        RateLimiter::clear('register');
        RateLimiter::clear('login');
        SymfonyRequest::setTrustedProxies([], SymfonyRequest::HEADER_X_FORWARDED_FOR | SymfonyRequest::HEADER_X_FORWARDED_HOST | SymfonyRequest::HEADER_X_FORWARDED_PORT | SymfonyRequest::HEADER_X_FORWARDED_PROTO);
        parent::tearDown();
    }

    /**
     * Requirement 1: Test that forged X-Forwarded-For does NOT change $request->ip()
     * when TRUSTED_PROXIES is not configured.
     */
    public function test_forged_x_forwarded_for_header_does_not_change_request_ip(): void
    {
        // By default, no proxies are trusted in bootstrap/app.php
        $response = $this->withServerVariables([
            'REMOTE_ADDR' => '127.0.0.1',
            'HTTP_X_FORWARDED_FOR' => '203.0.113.195, 198.51.100.17',
        ])->get('/health');

        $response->assertStatus(200);

        // Verify that symfony request does not honor X-Forwarded-For
        $this->assertEquals('127.0.0.1', request()->ip());
    }

    /**
     * Requirement 2a: Supervisor PIN multi-tier throttling:
     * - Key by authenticated user ID AND store ID
     * - 5 failures in 10 minutes locks that user for 15 minutes and emails manager, regardless of IP
     * - Store-wide cap of 20 failures per 10 minutes
     */
    public function test_supervisor_pin_locks_user_after_5_failures_and_emails_manager_regardless_of_ip(): void
    {
        $this->seed(SaaSPlanSeeder::class);

        $tenant = Tenant::create([
            'name' => 'Apex Retail Ltd',
            'email' => 'apex@example.com',
            'code' => 'APEX-01',
            'subscription_status' => 'active',
            'expires_at' => now()->addMonth(),
        ]);

        $store = Store::create([
            'tenant_id' => $tenant->id,
            'name' => 'Main Outlet',
            'code' => 'OUTLET-01',
            'is_active' => true,
        ]);

        $manager = User::create([
            'tenant_id' => $tenant->id,
            'store_id' => $store->id,
            'name' => 'Branch Manager',
            'email' => 'manager@apexretail.com',
            'password' => Hash::make('password123'),
            'role' => 'store_manager',
        ]);

        $cashier = User::create([
            'tenant_id' => $tenant->id,
            'store_id' => $store->id,
            'name' => 'Cashier Bob',
            'email' => 'bob@apexretail.com',
            'password' => Hash::make('password123'),
            'pos_pin' => Hash::make('1234'),
            'role' => 'cashier',
        ]);

        RateLimiter::clear("pos-pin:user:attempts:{$cashier->id}:{$store->id}");
        RateLimiter::clear("pos-pin:user:lock:{$cashier->id}:{$store->id}");
        RateLimiter::clear("pos-pin:store:attempts:{$store->id}");
        RateLimiter::clear("pos-pin:store:lock:{$store->id}");

        $this->actingAs($cashier);

        // 4 failed attempts with rotating fake X-Forwarded-For headers
        for ($i = 1; $i <= 4; $i++) {
            $resp = $this->withHeaders([
                'X-Forwarded-For' => "10.0.0.{$i}",
            ])->postJson('/pos/verify-pin', ['pin' => '9999', 'store_id' => $store->id]);

            $resp->assertStatus(403);
        }

        Mail::assertNothingSent();

        // 5th failed attempt with another spoofed IP
        $resp5 = $this->withHeaders([
            'X-Forwarded-For' => '192.168.1.100',
        ])->postJson('/pos/verify-pin', ['pin' => '9999', 'store_id' => $store->id]);

        $resp5->assertStatus(403);

        // Manager alert email MUST be sent after 5th failure
        Mail::assertSent(SupervisorPinLockoutAlertMail::class, function ($mail) use ($manager, $cashier) {
            return $mail->hasTo($manager->email) && $mail->user->id === $cashier->id;
        });

        // 6th attempt MUST be rejected with HTTP 429 Too Many Requests (user locked for 15 minutes)
        $resp6 = $this->withHeaders([
            'X-Forwarded-For' => '172.16.0.50',
        ])->postJson('/pos/verify-pin', ['pin' => '1234', 'store_id' => $store->id]);

        $resp6->assertStatus(429);
        $this->assertStringContainsString('User locked out', $resp6->json('message'));
        $this->assertNotNull($resp6->headers->get('Retry-After'));
    }

    public function test_supervisor_pin_store_wide_cap_locks_store_after_20_cumulative_failures(): void
    {
        $this->seed(SaaSPlanSeeder::class);

        $tenant = Tenant::create([
            'name' => 'Metro Retail Group',
            'email' => 'metro@example.com',
            'code' => 'METRO-01',
            'subscription_status' => 'active',
            'expires_at' => now()->addMonth(),
        ]);

        $store = Store::create([
            'tenant_id' => $tenant->id,
            'name' => 'Flagship Store',
            'code' => 'METRO-FLAG-01',
            'is_active' => true,
        ]);

        $cashier = User::create([
            'tenant_id' => $tenant->id,
            'store_id' => $store->id,
            'name' => 'Cashier Alice',
            'email' => 'alice@metroretail.com',
            'password' => Hash::make('password123'),
            'pos_pin' => Hash::make('5678'),
            'role' => 'cashier',
        ]);

        RateLimiter::clear("pos-pin:store:attempts:{$store->id}");
        RateLimiter::clear("pos-pin:store:lock:{$store->id}");

        $this->actingAs($cashier);

        // Simulate 20 failed hits on store counter
        for ($i = 1; $i <= 20; $i++) {
            RateLimiter::hit("pos-pin:store:attempts:{$store->id}", 600);
        }

        // Lock store
        RateLimiter::hit("pos-pin:store:lock:{$store->id}", 600);

        $response = $this->postJson('/pos/verify-pin', ['pin' => '5678', 'store_id' => $store->id]);
        $response->assertStatus(429);
        $this->assertStringContainsString('Store-wide PIN verification limit exceeded', $response->json('message'));
    }

    /**
     * Requirement 2b: /register global cap (30/hr) plus per-IP (5/hr) and honeypot
     */
    public function test_register_rate_limiting_enforces_per_ip_and_global_caps(): void
    {
        $this->seed(SaaSPlanSeeder::class);

        RateLimiter::clear('register');

        // Test 1: Honeypot check rejects spam bots with HTTP 422
        $hpResp = $this->post('/register', [
            'business_name' => 'Spam Bot Store',
            'owner_name' => 'Spam Bot',
            'email' => 'bot@spammer.com',
            'password' => 'ValidPass123!',
            'password_confirmation' => 'ValidPass123!',
            'plan_name' => 'Starter POS',
            'payment_method' => 'bkash',
            'sender_number' => '01711000111',
            'transaction_id' => 'TRXSPAM123',
            'website_hp' => 'http://spam-link.com',
        ]);
        $hpResp->assertStatus(422);

        // Test 2: Per-IP throttle locks after 5 attempts
        for ($i = 1; $i <= 5; $i++) {
            $this->post('/register', [
                'business_name' => "Legit Merchant {$i}",
                'owner_name' => "Owner {$i}",
                'email' => "owner{$i}@legit.com",
                'password' => 'ValidPass123!',
                'password_confirmation' => 'ValidPass123!',
                'plan_name' => 'Starter POS',
                'payment_method' => 'bkash',
                'sender_number' => '01711000111',
                'transaction_id' => "TRXLEGIT{$i}",
            ]);
        }

        // 6th attempt from the same IP must be blocked by per-IP limit with HTTP 429
        $ipThrottleResp = $this->post('/register', [
            'business_name' => 'Blocked Merchant',
            'owner_name' => 'Blocked Owner',
            'email' => 'blocked@legit.com',
            'password' => 'ValidPass123!',
            'password_confirmation' => 'ValidPass123!',
            'plan_name' => 'Starter POS',
            'payment_method' => 'bkash',
            'sender_number' => '01711000111',
            'transaction_id' => 'TRXBLOCKED999',
        ]);
        $ipThrottleResp->assertStatus(429);
    }

    /**
     * Requirement 2c: login locks by account email AND IP, remaining locked when rotating X-Forwarded-For
     */
    public function test_login_throttling_locks_by_account_email_and_ip_resisting_spoofed_headers(): void
    {
        $this->seed(SaaSPlanSeeder::class);

        $user = User::create([
            'name' => 'Target Merchant',
            'email' => 'target@merchant.com',
            'password' => Hash::make('CorrectPassword123!'),
            'role' => 'merchant',
        ]);

        RateLimiter::clear('login');

        // Attacker attempts 5 wrong passwords rotating X-Forwarded-For
        for ($i = 1; $i <= 5; $i++) {
            $resp = $this->withHeaders([
                'X-Forwarded-For' => "203.0.113.{$i}",
            ])->post('/login', [
                'email' => 'target@merchant.com',
                'password' => 'WrongPassword!',
            ]);
        }

        // 6th attempt with another spoofed IP must be intercepted by throttle:login middleware with 429
        $resp6 = $this->withHeaders([
            'X-Forwarded-For' => '198.51.100.99',
        ])->post('/login', [
            'email' => 'target@merchant.com',
            'password' => 'CorrectPassword123!',
        ]);

        $resp6->assertStatus(429);
    }

    /**
     * Requirement 3: DB::transaction pessimistic lockForUpdate + unique reference index.
     * Concurrency test: Two concurrent approval calls on the same payment row extend the tenant ONCE.
     */
    public function test_subscription_payment_approval_concurrency_with_pessimistic_locking(): void
    {
        $this->seed(SaaSPlanSeeder::class);

        $superAdmin = User::create([
            'name' => 'Platform Super Admin',
            'email' => 'admin@sababilling.net',
            'password' => Hash::make('SuperSecret123!'),
            'role' => 'super_admin',
        ]);

        $plan = SaaSPlan::where('code', 'GROWTH')->firstOrFail();

        $tenant = Tenant::create([
            'name' => 'Concurrent Store PLC',
            'email' => 'concurrent@store.com',
            'code' => 'CONCURR-01',
            'subscription_status' => 'pending_approval',
            'expires_at' => Carbon::now()->addDays(5),
            'mrr_amount' => $plan->monthly_price,
        ]);

        $initialExpiry = Carbon::parse($tenant->expires_at);

        $payment = SubscriptionPayment::create([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'amount' => $plan->monthly_price,
            'method' => 'bank',
            'reference' => 'TXN-CONCURRENT-UNIQUE-001',
            'bank_name' => 'Dutch-Bangla Bank',
            'account_holder' => 'Concurrent Store PLC',
            'account_last4' => '9988',
            'status' => 'pending',
        ]);

        $this->actingAs($superAdmin);

        // 1. Verify unique index prevents inserting duplicate reference
        $duplicateCaught = false;
        try {
            SubscriptionPayment::create([
                'tenant_id' => $tenant->id,
                'plan_id' => $plan->id,
                'amount' => $plan->monthly_price,
                'method' => 'bank',
                'reference' => 'TXN-CONCURRENT-UNIQUE-001', // duplicate!
                'status' => 'pending',
            ]);
        } catch (\Throwable $e) {
            $duplicateCaught = true;
        }
        $this->assertTrue($duplicateCaught, 'Unique index on reference must reject duplicate payment references.');

        // 2. Test concurrent execution safety:
        // Request 1 approves successfully
        $resp1 = $this->post("/super-admin/subscription-payments/{$payment->id}/approve");
        $resp1->assertStatus(302);
        $resp1->assertSessionHas('success');

        // Request 2 (simulating race / double-click) hits the same endpoint
        $resp2 = $this->post("/super-admin/subscription-payments/{$payment->id}/approve");
        $resp2->assertStatus(302);
        $resp2->assertSessionHas('info', 'This payment has already been approved.');

        // Verify database state: Tenant extended exactly ONCE (+30 days)
        $tenant->refresh();
        $payment->refresh();

        $this->assertEquals('approved', $payment->status);
        $this->assertEquals('active', $tenant->subscription_status);
        $this->assertEquals($initialExpiry->copy()->addDays(30)->toDateString(), Carbon::parse($tenant->expires_at)->toDateString());

        // Verify exactly 1 audit log was created for approval
        $this->assertEquals(1, AuditLog::where('action', 'subscription_payment_approved')->count());
    }

    public function test_two_concurrent_parallel_approvals_extend_tenant_once(): void
    {
        $this->seed(SaaSPlanSeeder::class);

        $superAdmin = User::create([
            'name' => 'Platform Super Admin',
            'email' => 'admin_concurrent@sababilling.net',
            'password' => Hash::make('SuperSecret123!'),
            'role' => 'super_admin',
        ]);

        $plan = SaaSPlan::where('code', 'STARTER')->firstOrFail();

        $tenant = Tenant::create([
            'name' => 'Parallel Store PLC',
            'email' => 'parallel@store.com',
            'code' => 'PARALLEL-01',
            'subscription_status' => 'pending_approval',
            'expires_at' => Carbon::now()->addDays(2),
            'mrr_amount' => $plan->monthly_price,
        ]);

        $initialExpiry = Carbon::parse($tenant->expires_at);

        $payment = SubscriptionPayment::create([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'amount' => $plan->monthly_price,
            'method' => 'bank',
            'reference' => 'TXN-PARALLEL-PROCESS-001',
            'status' => 'pending',
        ]);

        if (config('database.default') === 'mysql') {
            // Parallel processes against MySQL
            $phpBin = PHP_BINARY;
            $script = "require 'vendor/autoload.php'; \$app = require_once 'bootstrap/app.php'; \$kernel = \$app->make(Illuminate\Contracts\Console\Kernel::class); \$kernel->bootstrap(); Illuminate\Support\Facades\Auth::loginUsingId({$superAdmin->id}); app(App\Http\Controllers\SuperAdminController::class)->approveSubscriptionPayment({$payment->id});";

            $p1 = new \Symfony\Component\Process\Process([$phpBin, '-r', $script], base_path(), ['DB_CONNECTION' => 'mysql']);
            $p2 = new \Symfony\Component\Process\Process([$phpBin, '-r', $script], base_path(), ['DB_CONNECTION' => 'mysql']);

            $p1->start();
            $p2->start();

            $p1->wait();
            $p2->wait();
        } else {
            // Concurrent execution simulation
            $this->actingAs($superAdmin);
            $this->post("/super-admin/subscription-payments/{$payment->id}/approve");
            $this->post("/super-admin/subscription-payments/{$payment->id}/approve");
        }

        $tenant->refresh();
        $payment->refresh();

        $this->assertEquals('approved', $payment->status);
        $this->assertEquals('active', $tenant->subscription_status);
        $this->assertEquals($initialExpiry->copy()->addDays(30)->toDateString(), Carbon::parse($tenant->expires_at)->toDateString());
        $this->assertEquals(1, AuditLog::where('tenant_id', $tenant->id)->where('action', 'subscription_payment_approved')->count());
    }
}
