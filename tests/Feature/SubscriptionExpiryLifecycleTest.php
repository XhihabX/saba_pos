<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Order;
use App\Models\Product;
use App\Models\RegisterShift;
use App\Models\Stock;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class SubscriptionExpiryLifecycleTest extends TestCase
{
    use DatabaseMigrations;

    protected Tenant $tenant;
    protected Store $store;
    protected Product $product;
    protected User $merchant;
    protected User $manager;
    protected User $cashier;
    protected string $tz;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tz = config('subscription.timezone', 'Asia/Dhaka');

        $this->tenant = Tenant::create([
            'name' => 'Lifecycle Superstore',
            'code' => 'TEN-LIFE-01',
            'email' => 'lifecycle@example.com',
            'subscription_status' => 'active',
            'expires_at' => Carbon::now($this->tz)->addDays(30),
            'grace_days' => 5,
            'allow_suspended_read_only' => false,
        ]);

        $this->store = Store::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Main Outlet',
            'code' => 'STORE-LIFE-01',
            'currency_symbol' => '৳',
            'is_vat_registered' => false,
        ]);

        $this->product = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Test Product',
            'sku' => 'LIFE-SKU-01',
            'selling_price' => 100.00,
            'cost_price' => 70.00,
            'vat_rate' => 0.00,
            'vat_type' => 'inclusive',
        ]);

        Stock::create([
            'tenant_id' => $this->tenant->id,
            'store_id' => $this->store->id,
            'product_id' => $this->product->id,
            'quantity' => 1000.00,
        ]);

        $this->merchant = User::create([
            'tenant_id' => $this->tenant->id,
            'store_id' => $this->store->id,
            'name' => 'Store Merchant Owner',
            'email' => 'merchant@lifecycle.com',
            'password' => Hash::make('secret'),
            'role' => 'merchant',
            'is_approved' => true,
        ]);

        $this->manager = User::create([
            'tenant_id' => $this->tenant->id,
            'store_id' => $this->store->id,
            'name' => 'Branch Manager',
            'email' => 'manager@lifecycle.com',
            'password' => Hash::make('secret'),
            'role' => 'store_manager',
            'is_approved' => true,
        ]);

        $this->cashier = User::create([
            'tenant_id' => $this->tenant->id,
            'store_id' => $this->store->id,
            'name' => 'Terminal Cashier',
            'email' => 'cashier@lifecycle.com',
            'password' => Hash::make('secret'),
            'role' => 'cashier',
            'is_approved' => true,
        ]);

        // Open shifts for checkout eligibility
        foreach ([$this->merchant, $this->manager, $this->cashier] as $u) {
            RegisterShift::create([
                'tenant_id' => $this->tenant->id,
                'store_id' => $this->store->id,
                'user_id' => $u->id,
                'opening_balance' => 500.00,
                'status' => 'open',
                'opened_at' => now(),
            ]);
        }
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    protected function makeCheckoutPayload(array $overrides = []): array
    {
        $uuid = 'UUID-' . Str::uuid();

        return array_merge([
            'store_id' => $this->store->id,
            'client_uuid' => $uuid,
            'idempotency_key' => $uuid,
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 1],
            ],
            'paid_amount' => 100.00,
            'payment_method' => 'cash',
        ], $overrides);
    }

    // =========================================================================
    // STATE 1: ACTIVE (UNEXPIRED)
    // =========================================================================
    public function test_state_1_active_unexpired_allows_all_roles_and_json_checkout(): void
    {
        $now = Carbon::create(2026, 10, 1, 10, 0, 0, $this->tz);
        Carbon::setTestNow($now);

        $this->tenant->update([
            'subscription_status' => 'active',
            'expires_at' => $now->copy()->addDays(30),
        ]);

        // HTML checks
        $this->actingAs($this->merchant)->get('/merchant/dashboard')->assertStatus(200);
        $this->actingAs($this->manager)->get('/manager/dashboard')->assertStatus(200);
        $this->actingAs($this->cashier)->get('/pos')->assertStatus(200);

        // JSON checkout
        $response = $this->actingAs($this->cashier)
            ->postJson('/pos/checkout', $this->makeCheckoutPayload());
        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
    }

    // =========================================================================
    // STATE 2: WITHIN GRACE PERIOD
    // =========================================================================
    public function test_state_2_within_grace_period_allows_all_roles_with_banner(): void
    {
        $base = Carbon::create(2026, 10, 1, 10, 0, 0, $this->tz);
        // Expiry was 2 days ago, grace is 5 days -> 3 days remaining in grace
        $this->tenant->update([
            'subscription_status' => 'active',
            'expires_at' => $base->copy()->subDays(2),
            'grace_days' => 5,
        ]);
        Carbon::setTestNow($base);

        $this->assertTrue($this->tenant->isExpired());
        $this->assertTrue($this->tenant->isInGracePeriod());
        $this->assertFalse($this->tenant->isGraceExpired());
        $this->assertSame(3, $this->tenant->graceDaysRemaining());

        // HTML pages continue working
        $this->actingAs($this->merchant)->get('/merchant/dashboard')->assertStatus(200);
        $this->actingAs($this->manager)->get('/manager/dashboard')->assertStatus(200);
        $this->actingAs($this->cashier)->get('/pos')->assertStatus(200);

        // JSON checkouts continue working
        $response = $this->actingAs($this->cashier)
            ->postJson('/pos/checkout', $this->makeCheckoutPayload());
        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
    }

    // =========================================================================
    // STATE 3: PAST GRACE PERIOD, WITHIN 90-DAY READ-ONLY WINDOW
    // =========================================================================
    public function test_state_3_past_grace_within_read_only_window_routing_and_json_402(): void
    {
        $expiry = Carbon::create(2026, 10, 1, 0, 0, 0, $this->tz);
        // Grace ended at Oct 6. Now is Oct 10 (4 days after grace ended, well within 90-day read-only window)
        $now = Carbon::create(2026, 10, 10, 12, 0, 0, $this->tz);
        Carbon::setTestNow($now);

        $this->tenant->update([
            'subscription_status' => 'active',
            'expires_at' => $expiry,
            'grace_days' => 5,
        ]);

        $this->assertTrue($this->tenant->isGraceExpired());
        $this->assertTrue($this->tenant->isReadOnlyAllowed());

        // HTML non-read-only routes:
        // Merchant owner -> redirected to renewal page /merchant/subscription
        $this->actingAs($this->merchant)->get('/dashboard')
            ->assertRedirect(route('merchant.subscription'));

        // Store manager -> redirected to /subscription-expired
        $this->actingAs($this->manager)->get('/manager/shifts')
            ->assertRedirect(route('subscription.expired'));

        // Cashier -> redirected to /subscription-expired
        $this->actingAs($this->cashier)->get('/pos')
            ->assertRedirect(route('subscription.expired'));

        // Expired pages themselves are accessible without redirect loops
        $this->actingAs($this->merchant)->get(route('merchant.subscription'))->assertStatus(200);
        $this->actingAs($this->cashier)->get(route('subscription.expired'))->assertStatus(200);

        // HTML read-only routes:
        // Merchant can view reports & exports
        $this->actingAs($this->merchant)->get('/reports/profit-loss')->assertStatus(200);
        $this->actingAs($this->merchant)->get('/reports/sales/export-csv')->assertStatus(200);

        // Manager can view reports
        $this->actingAs($this->manager)->get('/reports/profit-loss')->assertStatus(200);

        // Cashier MUST STILL BE BLOCKED from reports by role middleware (never reaches reports even while read-only)
        $this->actingAs($this->cashier)->get('/reports/profit-loss')
            ->assertRedirect(route('login'));

        // Mutations / POST requests in read-only mode return HTTP 402 for JSON
        $this->actingAs($this->merchant)->postJson('/pos/checkout', $this->makeCheckoutPayload())
            ->assertStatus(402)
            ->assertJson(['message' => 'Subscription expired. Please ask the store owner to renew.']);

        $this->actingAs($this->cashier)->postJson('/pos/checkout', $this->makeCheckoutPayload())
            ->assertStatus(402)
            ->assertJson(['message' => 'Subscription expired. Please ask the store owner to renew.']);

        // Search endpoints return HTTP 402
        $this->actingAs($this->cashier)->getJson('/pos/products/search?q=test')
            ->assertStatus(402);
    }

    // =========================================================================
    // STATE 3 EXCEPTION: OFFLINE QUEUE LATE-SYNC
    // =========================================================================
    public function test_state_3_offline_sync_exception_accepts_valid_sync_marks_late_synced_and_logs_audit(): void
    {
        $expiry = Carbon::create(2026, 10, 1, 0, 0, 0, $this->tz);
        // Grace ended at Oct 6. Now is Oct 10 (4 days after grace ended; server receives within 7 days)
        $now = Carbon::create(2026, 10, 10, 12, 0, 0, $this->tz);
        Carbon::setTestNow($now);

        $this->tenant->update([
            'subscription_status' => 'active',
            'expires_at' => $expiry,
            'grace_days' => 5,
        ]);

        // Client time was created before grace ended (e.g. Oct 4)
        $clientTime = Carbon::create(2026, 10, 4, 15, 30, 0, $this->tz)->toIso8601String();
        $idempotencyKey = 'OFFLINE-KEY-' . Str::uuid();

        $payload = $this->makeCheckoutPayload([
            'idempotency_key' => $idempotencyKey,
            'client_uuid' => $idempotencyKey,
            'is_offline_sync' => true,
            'client_time' => $clientTime,
        ]);

        $response = $this->actingAs($this->cashier)->postJson('/pos/checkout', $payload);
        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        // Order must be marked late_synced = true
        $order = Order::where('idempotency_key', $idempotencyKey)->first();
        $this->assertNotNull($order);
        $this->assertTrue((bool) $order->late_synced);

        // Audit log entry must be written
        $this->assertDatabaseHas('audit_logs', [
            'tenant_id' => $this->tenant->id,
            'action' => 'late_offline_sync',
        ]);
    }

    public function test_state_3_rejects_sync_if_client_time_was_after_end_of_grace(): void
    {
        $expiry = Carbon::create(2026, 10, 1, 0, 0, 0, $this->tz);
        // Grace ended at Oct 6 00:00:00. Now is Oct 8.
        $now = Carbon::create(2026, 10, 8, 12, 0, 0, $this->tz);
        Carbon::setTestNow($now);

        $this->tenant->update([
            'subscription_status' => 'active',
            'expires_at' => $expiry,
            'grace_days' => 5,
        ]);

        // Client time was AFTER grace ended (Oct 7)
        $clientTime = Carbon::create(2026, 10, 7, 10, 0, 0, $this->tz)->toIso8601String();
        $idempotencyKey = 'INVALID-TIME-' . Str::uuid();

        $payload = $this->makeCheckoutPayload([
            'idempotency_key' => $idempotencyKey,
            'is_offline_sync' => true,
            'client_time' => $clientTime,
        ]);

        $response = $this->actingAs($this->cashier)->postJson('/pos/checkout', $payload);
        $response->assertStatus(402);
        $response->assertJson(['message' => 'Subscription expired. Please ask the store owner to renew.']);
    }

    public function test_state_3_rejects_back_dated_sync_received_after_7_day_late_window(): void
    {
        $expiry = Carbon::create(2026, 10, 1, 0, 0, 0, $this->tz);
        // Grace ended at Oct 6. 7-day late window ended at Oct 13.
        // Server receives it on Oct 14 (8 days after grace ended -> beyond 7-day window)
        $now = Carbon::create(2026, 10, 14, 10, 0, 0, $this->tz);
        Carbon::setTestNow($now);

        $this->tenant->update([
            'subscription_status' => 'active',
            'expires_at' => $expiry,
            'grace_days' => 5,
        ]);

        // Back-dated client time (claiming sale happened on Oct 2, before grace ended)
        $clientTime = Carbon::create(2026, 10, 2, 10, 0, 0, $this->tz)->toIso8601String();
        $idempotencyKey = 'BACK-DATED-LATE-' . Str::uuid();

        $payload = $this->makeCheckoutPayload([
            'idempotency_key' => $idempotencyKey,
            'is_offline_sync' => true,
            'client_time' => $clientTime,
        ]);

        // Must be rejected with HTTP 402 because server received it outside the 7-day window!
        $response = $this->actingAs($this->cashier)->postJson('/pos/checkout', $payload);
        $response->assertStatus(402);
        $response->assertJson(['message' => 'Subscription expired. Please ask the store owner to renew.']);
    }

    // =========================================================================
    // STATE 4: PAST READ-ONLY WINDOW (> 90 DAYS AFTER EXPIRATION)
    // =========================================================================
    public function test_state_4_past_read_only_window_blocks_all_roles_even_on_reports(): void
    {
        $expiry = Carbon::create(2026, 1, 1, 0, 0, 0, $this->tz);
        // Now is 100 days after expiration
        $now = Carbon::create(2026, 4, 11, 10, 0, 0, $this->tz);
        Carbon::setTestNow($now);

        $this->tenant->update([
            'subscription_status' => 'active',
            'expires_at' => $expiry,
            'grace_days' => 5,
        ]);

        $this->assertFalse($this->tenant->isReadOnlyAllowed());

        // Merchant redirected to renewal page even for reports
        $this->actingAs($this->merchant)->get('/reports/profit-loss')
            ->assertRedirect(route('merchant.subscription'));

        // Manager redirected to expired page even for reports
        $this->actingAs($this->manager)->get('/reports/profit-loss')
            ->assertRedirect(route('subscription.expired'));

        // Cashier redirected to expired page
        $this->actingAs($this->cashier)->get('/pos')
            ->assertRedirect(route('subscription.expired'));

        // JSON mutation returns 402
        $this->actingAs($this->merchant)->postJson('/pos/checkout', $this->makeCheckoutPayload())
            ->assertStatus(402);
    }

    // =========================================================================
    // STATE 5: SUSPENDED WITHOUT READ-ONLY
    // =========================================================================
    public function test_state_5_suspended_without_read_only_blocks_immediately(): void
    {
        $this->tenant->update([
            'subscription_status' => 'suspended',
            'allow_suspended_read_only' => false,
            'expires_at' => now()->addDays(30), // Even if expires_at is future!
        ]);

        // HTML routing
        $this->actingAs($this->merchant)->get('/reports/profit-loss')
            ->assertRedirect(route('merchant.subscription'));
        $this->actingAs($this->manager)->get('/reports/profit-loss')
            ->assertRedirect(route('subscription.expired'));
        $this->actingAs($this->cashier)->get('/pos')
            ->assertRedirect(route('subscription.expired'));

        // JSON calls get 402
        $this->actingAs($this->cashier)->postJson('/pos/checkout', $this->makeCheckoutPayload())
            ->assertStatus(402)
            ->assertJson(['message' => 'Tenant subscription is suspended.']);

        // Even offline sync gets 402 (no late sync for suspended)
        $payload = $this->makeCheckoutPayload([
            'is_offline_sync' => true,
            'client_time' => now()->subDay()->toIso8601String(),
        ]);
        $this->actingAs($this->cashier)->postJson('/pos/checkout', $payload)
            ->assertStatus(402)
            ->assertJson(['message' => 'Tenant subscription is suspended.']);
    }

    // =========================================================================
    // STATE 6: SUSPENDED WITH READ-ONLY FLAG
    // =========================================================================
    public function test_state_6_suspended_with_read_only_flag_allows_reports_for_managers(): void
    {
        $this->tenant->update([
            'subscription_status' => 'suspended',
            'allow_suspended_read_only' => true,
        ]);

        $this->assertTrue($this->tenant->isReadOnlyAllowed());

        // Merchant can view read-only reports
        $this->actingAs($this->merchant)->get('/reports/profit-loss')->assertStatus(200);

        // Store Manager can view read-only reports
        $this->actingAs($this->manager)->get('/reports/profit-loss')->assertStatus(200);

        // Cashier is STILL blocked by role middleware
        $this->actingAs($this->cashier)->get('/reports/profit-loss')
            ->assertRedirect(route('login'));

        // Non-read-only routes remain blocked
        $this->actingAs($this->merchant)->get('/pos')
            ->assertRedirect(route('merchant.subscription'));

        // Mutations and checkouts are BLOCKED (HTTP 402)
        $this->actingAs($this->merchant)->postJson('/pos/checkout', $this->makeCheckoutPayload())
            ->assertStatus(402);
    }

    // =========================================================================
    // BOUNDARY TESTS (Asia/Dhaka)
    // =========================================================================
    public function test_boundary_conditions_in_asia_dhaka(): void
    {
        $expiry = Carbon::create(2026, 10, 11, 23, 59, 59, $this->tz);
        $graceDays = 5;
        $lateSyncDays = 7;

        $this->tenant->update([
            'subscription_status' => 'active',
            'expires_at' => $expiry,
            'grace_days' => $graceDays,
        ]);

        // Boundary 1: Exactly at expires_at
        Carbon::setTestNow($expiry);
        $this->assertTrue($this->tenant->isExpired());
        $this->assertTrue($this->tenant->isInGracePeriod());
        $this->assertFalse($this->tenant->isGraceExpired());

        // Boundary 2: expires_at + 1 minute -> within grace period
        Carbon::setTestNow($expiry->copy()->addMinute());
        $this->assertTrue($this->tenant->isExpired());
        $this->assertTrue($this->tenant->isInGracePeriod());
        $this->assertFalse($this->tenant->isGraceExpired());

        // Boundary 3: Exactly at end of grace (expires_at + 5 days)
        $endOfGrace = $expiry->copy()->addDays($graceDays);
        Carbon::setTestNow($endOfGrace);
        $this->assertTrue($this->tenant->isInGracePeriod());
        $this->assertFalse($this->tenant->isGraceExpired());

        // Boundary 4: end of grace + 1 second -> grace expired
        Carbon::setTestNow($endOfGrace->copy()->addSecond());
        $this->assertFalse($this->tenant->isInGracePeriod());
        $this->assertTrue($this->tenant->isGraceExpired());
        $this->assertTrue($this->tenant->isWithinLateSyncWindow());

        // Boundary 5: Exactly at end of late-sync window (end of grace + 7 days)
        $endOfLateSync = $endOfGrace->copy()->addDays($lateSyncDays);
        Carbon::setTestNow($endOfLateSync);
        $this->assertTrue($this->tenant->isWithinLateSyncWindow());

        // Boundary 6: end of late-sync window + 1 second -> late-sync window closed
        Carbon::setTestNow($endOfLateSync->copy()->addSecond());
        $this->assertFalse($this->tenant->isWithinLateSyncWindow());
    }
}
