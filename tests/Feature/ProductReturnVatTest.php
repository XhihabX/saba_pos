<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductReturn;
use App\Models\RegisterShift;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProductReturnVatTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_refund_including_proportional_vat_is_allowed(): void
    {
        $tenant = Tenant::create([
            'name' => 'Return VAT Merchant',
            'code' => 'return-vat-test',
            'email' => 'returnvat@example.com',
            'status' => 'active',
            'subscription_status' => 'active',
            'subscription_expires_at' => now()->addDays(30),
        ]);

        $store = Store::create([
            'tenant_id' => $tenant->id,
            'name' => 'Main Outlet',
            'code' => 'STORE-RET-01',
            'default_tax_rate' => 15.00,
            'vat_mode' => 'exclusive',
        ]);

        $user = User::create([
            'tenant_id' => $tenant->id,
            'store_id' => $store->id,
            'name' => 'Manager User',
            'email' => 'manager.ret@example.com',
            'password' => Hash::make('password123'),
            'role' => 'merchant',
        ]);

        $product = Product::create([
            'tenant_id' => $tenant->id,
            'name' => 'Taxable Item',
            'sku' => 'RET-VAT-001',
            'selling_price' => 100.00,
            'purchase_cost' => 60.00,
            'vat_rate' => 15.00,
            'vat_mode' => 'exclusive',
            'is_active' => true,
        ]);

        RegisterShift::create([
            'tenant_id' => $tenant->id,
            'store_id' => $store->id,
            'user_id' => $user->id,
            'opening_cash' => 500.00,
            'status' => 'open',
            'opened_at' => now(),
        ]);

        $this->actingAs($user);

        $order = Order::create([
            'tenant_id' => $tenant->id,
            'store_id' => $store->id,
            'user_id' => $user->id,
            'invoice_no' => 'INV-RET-1001',
            'subtotal' => 100.00,
            'discount_amount' => 0.00,
            'tax_amount' => 15.00,
            'grand_total' => 115.00,
            'paid_amount' => 115.00,
            'change_return' => 0.00,
            'payment_status' => 'paid',
            'payment_method' => 'cash',
        ]);

        OrderItem::create([
            'tenant_id' => $tenant->id,
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => 1,
            'unit_price' => 100.00,
            'cost_price' => 60.00,
            'discount' => 0.00,
            'vat_rate' => 15.00,
            'vat_amount' => 15.00,
            'vat_mode' => 'exclusive',
            'total' => 100.00,
        ]);

        // Refund full paid amount (100 price + 15 VAT = 115)
        $response = $this->post('/manager/returns', [
            'invoice_no' => $order->invoice_no,
            'product_id' => $product->id,
            'quantity' => 1,
            'refund_amount' => 115.00,
            'reason' => 'Customer changed mind',
        ]);

        $response->assertStatus(302);
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('product_returns', [
            'order_id' => $order->id,
            'product_id' => $product->id,
            'refund_amount' => 115.00,
        ]);
    }

    public function test_over_refund_exceeding_paid_amount_with_vat_is_rejected(): void
    {
        $tenant = Tenant::create([
            'name' => 'Return Over Merchant',
            'code' => 'return-over-test',
            'email' => 'returnover@example.com',
            'status' => 'active',
            'subscription_status' => 'active',
            'subscription_expires_at' => now()->addDays(30),
        ]);

        $store = Store::create([
            'tenant_id' => $tenant->id,
            'name' => 'Main Outlet',
            'code' => 'STORE-RET-02',
            'default_tax_rate' => 15.00,
            'vat_mode' => 'exclusive',
        ]);

        $user = User::create([
            'tenant_id' => $tenant->id,
            'store_id' => $store->id,
            'name' => 'Manager User',
            'email' => 'manager.over@example.com',
            'password' => Hash::make('password123'),
            'role' => 'merchant',
        ]);

        $product = Product::create([
            'tenant_id' => $tenant->id,
            'name' => 'Taxable Item 2',
            'sku' => 'RET-VAT-002',
            'selling_price' => 100.00,
            'purchase_cost' => 60.00,
            'vat_rate' => 15.00,
            'vat_mode' => 'exclusive',
            'is_active' => true,
        ]);

        $this->actingAs($user);

        $order = Order::create([
            'tenant_id' => $tenant->id,
            'store_id' => $store->id,
            'user_id' => $user->id,
            'invoice_no' => 'INV-RET-1002',
            'subtotal' => 100.00,
            'discount_amount' => 0.00,
            'tax_amount' => 15.00,
            'grand_total' => 115.00,
            'paid_amount' => 115.00,
            'change_return' => 0.00,
            'payment_status' => 'paid',
            'payment_method' => 'cash',
        ]);

        OrderItem::create([
            'tenant_id' => $tenant->id,
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => 1,
            'unit_price' => 100.00,
            'cost_price' => 60.00,
            'discount' => 0.00,
            'vat_rate' => 15.00,
            'vat_amount' => 15.00,
            'vat_mode' => 'exclusive',
            'total' => 100.00,
        ]);

        // Over-refund attempt of 125.00 (which exceeds paid amount of 115.00)
        $response = $this->post('/manager/returns', [
            'invoice_no' => $order->invoice_no,
            'product_id' => $product->id,
            'quantity' => 1,
            'refund_amount' => 125.00,
            'reason' => 'Fraudulent over refund request',
        ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors(['refund_amount']);

        $this->assertEquals(0, ProductReturn::where('order_id', $order->id)->count());
    }
}
