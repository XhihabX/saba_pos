<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\RegisterShift;
use App\Models\Stock;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CheckoutClientUuidTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_rejects_request_without_client_uuid(): void
    {
        $tenant = Tenant::create([
            'name' => 'UUID Test Merchant',
            'code' => 'uuid-test-merchant',
            'email' => 'uuid@example.com',
            'status' => 'active',
            'subscription_status' => 'active',
            'subscription_expires_at' => now()->addDays(30),
        ]);

        $store = Store::create([
            'tenant_id' => $tenant->id,
            'name' => 'Main Outlet',
            'code' => 'STORE-UUID-01',
            'default_tax_rate' => 15.00,
        ]);

        $user = User::create([
            'tenant_id' => $tenant->id,
            'store_id' => $store->id,
            'name' => 'Cashier UUID',
            'email' => 'cashier.uuid@example.com',
            'password' => Hash::make('password123'),
            'role' => 'cashier',
        ]);

        $product = Product::create([
            'tenant_id' => $tenant->id,
            'name' => 'UUID Item',
            'sku' => 'UUID-ITEM-001',
            'selling_price' => 100.00,
            'purchase_cost' => 60.00,
            'is_active' => true,
        ]);

        Stock::create([
            'tenant_id' => $tenant->id,
            'store_id' => $store->id,
            'product_id' => $product->id,
            'quantity' => 10.00,
        ]);

        RegisterShift::create([
            'tenant_id' => $tenant->id,
            'store_id' => $store->id,
            'user_id' => $user->id,
            'opening_cash' => 100.00,
            'status' => 'open',
            'opened_at' => now(),
        ]);

        $this->actingAs($user);

        // Attempt checkout without client_uuid or idempotency_key -> 422
        $response = $this->postJson('/pos/checkout', [
            'store_id' => $store->id,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
            'paid_amount' => 115.00,
            'payment_method' => 'cash',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['client_uuid']);
    }

    public function test_checkout_succeeds_when_client_uuid_is_provided(): void
    {
        $tenant = Tenant::create([
            'name' => 'UUID Success Merchant',
            'code' => 'uuid-success-merchant',
            'email' => 'uuidsuccess@example.com',
            'status' => 'active',
            'subscription_status' => 'active',
            'subscription_expires_at' => now()->addDays(30),
        ]);

        $store = Store::create([
            'tenant_id' => $tenant->id,
            'name' => 'Main Outlet',
            'code' => 'STORE-UUID-02',
            'default_tax_rate' => 15.00,
        ]);

        $user = User::create([
            'tenant_id' => $tenant->id,
            'store_id' => $store->id,
            'name' => 'Cashier UUID',
            'email' => 'cashier.uuid2@example.com',
            'password' => Hash::make('password123'),
            'role' => 'cashier',
        ]);

        $product = Product::create([
            'tenant_id' => $tenant->id,
            'name' => 'UUID Item 2',
            'sku' => 'UUID-ITEM-002',
            'selling_price' => 100.00,
            'purchase_cost' => 60.00,
            'is_active' => true,
        ]);

        Stock::create([
            'tenant_id' => $tenant->id,
            'store_id' => $store->id,
            'product_id' => $product->id,
            'quantity' => 10.00,
        ]);

        RegisterShift::create([
            'tenant_id' => $tenant->id,
            'store_id' => $store->id,
            'user_id' => $user->id,
            'opening_cash' => 100.00,
            'status' => 'open',
            'opened_at' => now(),
        ]);

        $this->actingAs($user);

        $clientUuid = 'f81d4fae-7dec-11d0-a765-00a0c91e6bf6';

        $response = $this->postJson('/pos/checkout', [
            'client_uuid' => $clientUuid,
            'store_id' => $store->id,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
            'paid_amount' => 115.00,
            'payment_method' => 'cash',
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('orders', [
            'tenant_id' => $tenant->id,
            'idempotency_key' => $clientUuid,
        ]);
    }
}
