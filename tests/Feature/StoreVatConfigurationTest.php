<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\RegisterShift;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class StoreVatConfigurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_store_defaults_to_15_percent_vat(): void
    {
        $tenant = Tenant::create([
            'name' => 'VAT Test Tenant',
            'code' => 'vat-tenant-test',
            'email' => 'vat@example.com',
            'status' => 'active',
        ]);

        $store = Store::create([
            'tenant_id' => $tenant->id,
            'name' => 'Default 15% Store',
            'code' => 'VAT-ST01',
        ]);

        $this->assertEquals(15.00, (float) $store->default_tax_rate);
    }

    public function test_store_vat_is_configurable_and_used_in_pos_checkout(): void
    {
        $tenant = Tenant::create([
            'name' => 'Configurable VAT Merchant',
            'code' => 'cfg-vat-merchant',
            'email' => 'cfgvat@example.com',
            'status' => 'active',
            'subscription_status' => 'active',
            'subscription_expires_at' => now()->addDays(30),
        ]);

        // Create store with custom 7.50% VAT rate
        $store = Store::create([
            'tenant_id' => $tenant->id,
            'name' => 'Custom 7.5% Store',
            'code' => 'VAT-ST02',
            'default_tax_rate' => 7.50,
            'vat_mode' => 'exclusive',
        ]);

        $user = User::create([
            'tenant_id' => $tenant->id,
            'store_id' => $store->id,
            'name' => 'Cashier VAT',
            'email' => 'cashier.vat@example.com',
            'password' => Hash::make('password123'),
            'role' => 'cashier',
        ]);

        $product = Product::create([
            'tenant_id' => $tenant->id,
            'name' => 'Standard Item',
            'sku' => 'VAT-ITEM-75',
            'selling_price' => 100.00,
            'purchase_cost' => 60.00,
            'is_active' => true,
        ]);

        \App\Models\Stock::create([
            'tenant_id' => $tenant->id,
            'store_id' => $store->id,
            'product_id' => $product->id,
            'quantity' => 20.00,
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

        // Exclusive VAT @ 7.5%: 100 subtotal + 7.50 VAT = 107.50 total
        $response = $this->postJson('/pos/checkout', [
            'store_id' => $store->id,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
            'paid_amount' => 107.50,
            'payment_method' => 'cash',
        ]);

        $response->assertStatus(200);

        $order = \App\Models\Order::with('items')->find($response->json('order.id'));
        $this->assertEquals(7.50, (float) $order->items->first()->vat_rate);
        $this->assertEquals(7.50, (float) $order->items->first()->vat_amount);
    }
}
