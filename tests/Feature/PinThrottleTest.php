<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class PinThrottleTest extends TestCase
{
    use RefreshDatabase;

    public function test_pos_verify_pin_throttles_after_5_failed_attempts(): void
    {
        $tenant = Tenant::create([
            'name' => 'Pin Throttle Merchant',
            'code' => 'pin-throttle-test',
            'email' => 'pin.merchant@example.com',
            'status' => 'active',
            'subscription_status' => 'active',
            'subscription_expires_at' => now()->addDays(30),
        ]);

        $user = User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Cashier User',
            'email' => 'cashier.throttle@example.com',
            'password' => Hash::make('password123'),
            'pos_pin' => Hash::make('1234'),
            'role' => 'cashier',
        ]);

        $key = "pos-pin-failed:{$user->id}:127.0.0.1";
        RateLimiter::clear($key);

        $this->actingAs($user);

        // First 5 failed attempts return 403 Invalid Cashier PIN
        for ($i = 1; $i <= 5; $i++) {
            $response = $this->postJson('/pos/verify-pin', ['pin' => '9999']);
            $response->assertStatus(403)
                ->assertJson(['success' => false, 'message' => 'Invalid Cashier PIN.']);
        }

        // 6th failed attempt triggers HTTP 429 Too Many Requests
        $response6 = $this->postJson('/pos/verify-pin', ['pin' => '9999']);
        $response6->assertStatus(429)
            ->assertJsonStructure(['success', 'message']);

        // Even with the correct PIN, request is rejected with HTTP 429 while locked out
        $responseCorrectLocked = $this->postJson('/pos/verify-pin', ['pin' => '1234']);
        $responseCorrectLocked->assertStatus(429);

        // Clear limiter state and verify correct PIN succeeds with HTTP 200
        RateLimiter::clear($key);
        $responseSuccess = $this->postJson('/pos/verify-pin', ['pin' => '1234']);
        $responseSuccess->assertStatus(200)
            ->assertJson(['success' => true]);
    }
}
