<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\PlatformSetting;
use App\Models\SaaSPlan;
use App\Models\Store;
use App\Models\SubscriptionPayment;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\SaaSPlanSeeder;
use Database\Seeders\SabaPosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MerchantRegistrationAndPlatformSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('5,60');
        Storage::fake('local');
    }

    protected function tearDown(): void
    {
        Request::setTrustedHosts([]);
        parent::tearDown();
    }

    public function test_pos_install_command_provisions_plans_and_super_admin(): void
    {
        $exitCode = Artisan::call('pos:install', [
            '--email' => 'owner_admin@sababilling.net',
            '--password' => 'SuperSecret123!',
            '--no-interaction' => true,
        ]);

        $this->assertEquals(0, $exitCode);

        $superAdmin = User::where('email', 'owner_admin@sababilling.net')->first();
        $this->assertNotNull($superAdmin);
        $this->assertEquals('super_admin', $superAdmin->role);
        $this->assertTrue(Hash::check('SuperSecret123!', $superAdmin->password));

        $this->assertGreaterThanOrEqual(3, SaaSPlan::count());
        $this->assertNotNull(SaaSPlan::where('code', 'STARTER')->first());
        $this->assertNotNull(SaaSPlan::where('code', 'GROWTH')->first());
        $this->assertNotNull(SaaSPlan::where('code', 'ENTERPRISE')->first());
    }

    public function test_saba_pos_seeder_aborts_with_runtime_exception_in_production(): void
    {
        config(['app.env' => 'production']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('SabaPosSeeder contains demo accounts and cannot be run in production! Use php artisan pos:install instead.');

        $seeder = new SabaPosSeeder();
        $seeder->run();
    }

    public function test_untrusted_host_header_returns_400_bad_request(): void
    {
        Request::setTrustedHosts(['^pos\.sababilling\.net$', '^sababilling\.net$']);

        $response = $this->withHeaders([
            'Host' => 'attacker-forged-domain.com',
        ])->get('/health');

        $response->assertStatus(400);
        $this->assertStringContainsString('Bad Request: Untrusted Host', $response->getContent());
    }

    public function test_public_registration_honeypot_field_rejects_bots(): void
    {
        $this->seed(SaaSPlanSeeder::class);

        $response = $this->post('/register', [
            'business_name' => 'Spam Bot Store',
            'owner_name' => 'Botty McBot',
            'email' => 'bot@spammer.com',
            'password' => 'ValidPassword123!',
            'password_confirmation' => 'ValidPassword123!',
            'plan_name' => 'Starter POS',
            'payment_method' => 'bkash',
            'sender_number' => '01711000111',
            'transaction_id' => 'TRXBOT123456',
            'website_hp' => 'http://spam-trap.com', // honeypot populated!
        ]);

        $response->assertStatus(422);
        $this->assertNull(Tenant::where('email', 'bot@spammer.com')->first());
    }

    public function test_public_registration_throttling_limits_to_5_per_hour_per_ip(): void
    {
        $this->seed(SaaSPlanSeeder::class);

        // Make 5 requests from same IP
        for ($i = 0; $i < 5; $i++) {
            $this->post('/register', [
                'business_name' => 'Test Store '.$i,
                'email' => 'invalid-email-format',
            ]);
        }

        // 6th request triggers 429 Too Many Requests
        $response = $this->post('/register', [
            'business_name' => 'Test Store 6',
            'email' => 'valid6@email.com',
        ]);

        $response->assertStatus(429);
    }

    public function test_public_registration_requires_unique_business_email(): void
    {
        $this->seed(SaaSPlanSeeder::class);

        Tenant::create([
            'name' => 'Existing Retailer',
            'code' => 'TENANT-EXIST',
            'email' => 'owner@retailer.com',
            'plan_name' => 'Starter POS',
            'subscription_status' => 'active',
            'mrr_amount' => 1499.00,
            'expires_at' => now()->addDays(30),
        ]);

        $response = $this->post('/register', [
            'business_name' => 'Duplicate Store Attempt',
            'owner_name' => 'Jane Doe',
            'email' => 'owner@retailer.com',
            'password' => 'ValidPassword123!',
            'password_confirmation' => 'ValidPassword123!',
            'plan_name' => 'Starter POS',
            'payment_method' => 'bkash',
            'sender_number' => '01711000222',
            'transaction_id' => 'TRXUNIQUE001',
        ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors(['email']);
    }

    public function test_bank_transfer_registration_stores_sanitized_fields_and_private_proof(): void
    {
        $this->seed(SaaSPlanSeeder::class);

        $slip = UploadedFile::fake()->create('bank_deposit_slip.pdf', 800, 'application/pdf');

        $response = $this->post('/register', [
            'business_name' => 'Green Enterprise',
            'owner_name' => 'Kazi Nazmul',
            'email' => 'nazmul@greenenterprise.com',
            'phone' => '01712345678',
            'password' => 'SecurePass1234!',
            'password_confirmation' => 'SecurePass1234!',
            'plan_name' => 'Growth Multi-Store',
            'payment_method' => 'bank',
            'bank_name' => 'Dutch-Bangla Bank PLC',
            'account_holder' => 'Green Enterprise Ltd',
            'account_last4' => '8821', // sanitized 4 digits
            'transaction_id' => 'WIRE-REF-998811',
            'proof_file' => $slip,
        ]);

        $response->assertStatus(302);
        $response->assertRedirect('/pending-approval');

        $tenant = Tenant::where('email', 'nazmul@greenenterprise.com')->first();
        $this->assertNotNull($tenant);
        $this->assertEquals('Dutch-Bangla Bank PLC', $tenant->bank_name);
        $this->assertEquals('Green Enterprise Ltd', $tenant->account_holder);
        $this->assertEquals('8821', $tenant->account_last4);
        $this->assertNotNull($tenant->proof_path);

        // Verify file stored on private local disk
        Storage::disk('local')->assertExists($tenant->proof_path);

        // Verify initial SubscriptionPayment record
        $payment = SubscriptionPayment::where('tenant_id', $tenant->id)->first();
        $this->assertNotNull($payment);
        $this->assertEquals('bank', $payment->method);
        $this->assertEquals('WIRE-REF-998811', $payment->reference);
        $this->assertEquals('pending', $payment->status);
        $this->assertEquals('8821', $payment->account_last4);
        $this->assertEquals($tenant->proof_path, $payment->proof_path);

        // Guest cannot download proof
        $guestResponse = $this->get("/super-admin/tenants/{$tenant->id}/proof");
        $guestResponse->assertStatus(302); // Redirected to login

        // Super Admin can download proof
        $superAdmin = User::factory()->create(['role' => 'super_admin']);
        $adminResponse = $this->actingAs($superAdmin)->get("/super-admin/tenants/{$tenant->id}/proof");
        $adminResponse->assertStatus(200);
    }

    public function test_mfs_registration_validates_sender_phone_regex(): void
    {
        $this->seed(SaaSPlanSeeder::class);

        // Invalid phone format
        $response = $this->post('/register', [
            'business_name' => 'Fashion House',
            'owner_name' => 'Tasnim Rahman',
            'email' => 'tasnim@fashion.com',
            'password' => 'ValidPassword123!',
            'password_confirmation' => 'ValidPassword123!',
            'plan_name' => 'Starter POS',
            'payment_method' => 'bkash',
            'sender_number' => '12345', // invalid
            'transaction_id' => 'TRXINVALID99',
        ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors(['sender_number']);
    }

    public function test_merchant_subscription_renewal_submission_preserves_tenant_status_and_expiry(): void
    {
        $this->seed(SaaSPlanSeeder::class);
        $plan = SaaSPlan::where('code', 'STARTER')->first();

        $originalExpiry = Carbon::now()->addDays(12);

        $tenant = Tenant::create([
            'name' => 'Active Boutique',
            'code' => 'TENANT-BOUTIQUE',
            'email' => 'boutique@example.com',
            'plan_name' => $plan->name,
            'subscription_status' => 'active',
            'mrr_amount' => $plan->monthly_price,
            'expires_at' => $originalExpiry,
        ]);

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'merchant',
            'email' => 'boutique_owner@example.com',
        ]);

        $slip = UploadedFile::fake()->create('renewal_receipt.png', 300, 'image/png');

        $response = $this->actingAs($user)->post('/merchant/subscription/renew', [
            'plan_id' => $plan->id,
            'amount' => $plan->monthly_price,
            'payment_method' => 'rocket',
            'reference' => 'RCK-TRX-556677',
            'sender_number' => '017112233441',
            'proof_file' => $slip,
        ]);

        $response->assertStatus(302);
        $response->assertSessionHas('success');

        // Check renewal payment record created with status pending
        $payment = SubscriptionPayment::where('reference', 'RCK-TRX-556677')->first();
        $this->assertNotNull($payment);
        $this->assertEquals('pending', $payment->status);
        $this->assertEquals($tenant->id, $payment->tenant_id);

        // Verify Tenant subscription_status and expires_at remain strictly UNCHANGED
        $tenant->refresh();
        $this->assertEquals('active', $tenant->subscription_status);
        $this->assertEquals($originalExpiry->toDateTimeString(), $tenant->expires_at->toDateTimeString());
    }

    public function test_subscription_renewal_rejects_amount_mismatch(): void
    {
        $this->seed(SaaSPlanSeeder::class);
        $plan = SaaSPlan::where('code', 'STARTER')->first();

        $tenant = Tenant::create([
            'name' => 'Discount Seeker',
            'code' => 'TENANT-DISC',
            'email' => 'disc@example.com',
            'plan_name' => $plan->name,
            'subscription_status' => 'active',
            'mrr_amount' => $plan->monthly_price,
            'expires_at' => now()->addDays(5),
        ]);

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'merchant',
        ]);

        // Submitted amount does not match plan price
        $response = $this->actingAs($user)->post('/merchant/subscription/renew', [
            'plan_id' => $plan->id,
            'amount' => 500.00, // Price is 1499.00
            'payment_method' => 'bkash',
            'sender_number' => '01711223344',
            'reference' => 'TRXDISC500',
        ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors(['amount']);
    }

    public function test_subscription_renewal_rejects_duplicate_reference_across_any_tenant(): void
    {
        $this->seed(SaaSPlanSeeder::class);
        $plan = SaaSPlan::where('code', 'STARTER')->first();

        $tenantA = Tenant::create([
            'name' => 'Tenant Alpha',
            'code' => 'TENANT-ALPHA',
            'email' => 'alpha@example.com',
            'plan_name' => $plan->name,
            'subscription_status' => 'active',
            'mrr_amount' => $plan->monthly_price,
        ]);

        SubscriptionPayment::create([
            'tenant_id' => $tenantA->id,
            'plan_id' => $plan->id,
            'amount' => $plan->monthly_price,
            'method' => 'bkash',
            'reference' => 'REUSED-REF-999',
            'status' => 'approved',
        ]);

        $tenantB = Tenant::create([
            'name' => 'Tenant Beta',
            'code' => 'TENANT-BETA',
            'email' => 'beta@example.com',
            'plan_name' => $plan->name,
            'subscription_status' => 'active',
            'mrr_amount' => $plan->monthly_price,
        ]);

        $userB = User::factory()->create([
            'tenant_id' => $tenantB->id,
            'role' => 'merchant',
        ]);

        // Tenant B tries to use Tenant A's reference
        $response = $this->actingAs($userB)->post('/merchant/subscription/renew', [
            'plan_id' => $plan->id,
            'amount' => $plan->monthly_price,
            'payment_method' => 'bkash',
            'sender_number' => '01711223344',
            'reference' => 'REUSED-REF-999',
        ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors(['reference']);
    }

    public function test_super_admin_approval_is_idempotent_and_extends_from_max_expiry_or_now(): void
    {
        $this->seed(SaaSPlanSeeder::class);
        $plan = SaaSPlan::where('code', 'GROWTH')->first();

        // Expired tenant (expires_at is in the past)
        $pastExpiry = Carbon::now()->subDays(5);
        $tenant = Tenant::create([
            'name' => 'Expired Tenant',
            'code' => 'TENANT-EXP',
            'email' => 'exp@example.com',
            'plan_name' => $plan->name,
            'subscription_status' => 'past_due',
            'mrr_amount' => $plan->monthly_price,
            'expires_at' => $pastExpiry,
        ]);

        $payment = SubscriptionPayment::create([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'amount' => $plan->monthly_price,
            'method' => 'bank',
            'reference' => 'APPROVAL-TEST-REF-1',
            'status' => 'pending',
        ]);

        $superAdmin = User::factory()->create(['role' => 'super_admin']);

        // 1st Approval
        $response1 = $this->actingAs($superAdmin)->post("/super-admin/subscription-payments/{$payment->id}/approve");
        $response1->assertStatus(302);
        $response1->assertSessionHas('success');

        $tenant->refresh();
        $payment->refresh();

        $this->assertEquals('approved', $payment->status);
        $this->assertEquals('active', $tenant->subscription_status);
        // Base was now(), so new expiry is ~now() + 30 days
        $expectedExpiry = Carbon::now()->addDays(30);
        $this->assertEquals($expectedExpiry->toDateString(), $tenant->expires_at->toDateString());

        // 2nd Approval attempt (idempotency check)
        $firstSavedExpiry = $tenant->expires_at->copy();
        $response2 = $this->actingAs($superAdmin)->post("/super-admin/subscription-payments/{$payment->id}/approve");
        $response2->assertStatus(302);
        $response2->assertSessionHas('info');

        $tenant->refresh();
        // Must NOT double extend
        $this->assertEquals($firstSavedExpiry->toDateTimeString(), $tenant->expires_at->toDateTimeString());

        // Check AuditLog entry written
        $auditLog = AuditLog::where('action', 'subscription_payment_approved')->where('tenant_id', $tenant->id)->first();
        $this->assertNotNull($auditLog);
        $this->assertStringContainsString('APPROVAL-TEST-REF-1', $auditLog->description);
    }

    public function test_super_admin_settings_persistence_regex_validation_and_audit_diff(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin']);

        PlatformSetting::set('bkash_number', '01700000000');

        $payload = [
            'app_name' => 'IOT POS Enterprise',
            'bkash_number' => '01711223344', // valid regex
            'nagad_number' => '01811223344', // valid regex
            'rocket_number' => '017112233445', // valid 12-digit regex
            'bank_name' => 'Dutch-Bangla Bank PLC',
            'bank_account_name' => 'Saba Technologies Ltd',
            'bank_account_number' => '101.120.99882',
            'bank_branch_name' => 'Banani Branch',
            'bank_routing_number' => '090271829',
            'bank_instructions' => 'Include tenant code in wire memo.',
            'support_phone' => '+880 1700 112233',
            'support_email' => 'support@sababilling.net',
            'currency_symbol' => '৳',
            'default_tax_rate' => 15.00,
            'maintenance_mode' => false,
        ];

        $response = $this->actingAs($superAdmin)->post('/super-admin/settings', $payload);
        $response->assertStatus(302);
        $response->assertSessionHas('success');

        $this->assertEquals('01711223344', PlatformSetting::get('bkash_number'));
        $this->assertEquals('Dutch-Bangla Bank PLC', PlatformSetting::get('bank_name'));
        $this->assertEquals('101.120.99882', PlatformSetting::get('bank_account_number'));

        // Check audit log diff recorded
        $log = AuditLog::where('action', 'platform_settings_updated')->latest()->first();
        $this->assertNotNull($log);
        $this->assertStringContainsString('bkash_number', $log->description);

        // Invalid phone regex rejection test
        $invalidPayload = $payload;
        $invalidPayload['bkash_number'] = '012345'; // invalid prefix and length
        $invalidResponse = $this->actingAs($superAdmin)->post('/super-admin/settings', $invalidPayload);
        $invalidResponse->assertStatus(302);
        $invalidResponse->assertSessionHasErrors(['bkash_number']);
    }

    public function test_maintenance_mode_locks_out_regular_users_but_permits_super_admin(): void
    {
        $this->seed(SaaSPlanSeeder::class);
        PlatformSetting::set('maintenance_mode', '1');

        $merchant = User::factory()->create(['role' => 'merchant']);
        $superAdmin = User::factory()->create(['role' => 'super_admin']);

        // Regular merchant access gets 503
        $merchantResponse = $this->actingAs($merchant)->get('/merchant/dashboard');
        $merchantResponse->assertStatus(503);

        // Super Admin access is permitted
        $superAdminResponse = $this->actingAs($superAdmin)->get('/super-admin/dashboard');
        $superAdminResponse->assertStatus(200);

        // Public /login page is permitted
        Auth::logout();
        $loginResponse = $this->get('/login');
        $loginResponse->assertStatus(200);
    }
}
