<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class VatReportGroupTest extends TestCase
{
    use RefreshDatabase;

    public function test_vat_report_and_profit_loss_queries_execute_cleanly_with_strict_group_by(): void
    {
        $tenant = Tenant::create([
            'name' => 'Strict SQL Tenant',
            'code' => 'STRICT-TENANT-01',
            'email' => 'strict@tenant.test',
            'phone' => '01700000001',
            'subscription_status' => 'active',
            'expires_at' => now()->addYear(),
        ]);

        $store = Store::create([
            'tenant_id' => $tenant->id,
            'name' => 'Strict Store Outlet',
            'code' => 'STRICT-ST-01',
            'bin_number' => '123456789-0001',
            'vat_number' => '987654321-0001',
            'is_active' => true,
        ]);

        $user = User::create([
            'tenant_id' => $tenant->id,
            'store_id' => $store->id,
            'name' => 'Strict User',
            'email' => 'strict@test.local',
            'password' => bcrypt('password'),
            'role' => 'merchant',
        ]);

        $product = Product::create([
            'tenant_id' => $tenant->id,
            'name' => 'Strict Product',
            'sku' => 'STRICT-SKU-01',
            'purchase_cost' => 80.00,
            'selling_price' => 100.00,
            'vat_rate' => 15.00,
            'vat_mode' => 'exclusive',
            'is_active' => true,
        ]);

        $order = Order::create([
            'tenant_id' => $tenant->id,
            'store_id' => $store->id,
            'user_id' => $user->id,
            'idempotency_key' => 'IDEM-STRICT-01',
            'invoice_no' => 'INV-STRICT-01',
            'subtotal' => 100.00,
            'tax_amount' => 15.00,
            'grand_total' => 115.00,
            'paid_amount' => 115.00,
            'payment_status' => 'paid',
            'payment_method' => 'cash',
        ]);

        OrderItem::create([
            'tenant_id' => $tenant->id,
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => 'Strict Product',
            'quantity' => 1,
            'unit_price' => 100.00,
            'cost_price' => 80.00,
            'vat_rate' => 15.00,
            'vat_amount' => 15.00,
            'total' => 115.00,
        ]);

        $this->actingAs($user);

        // Test VAT Report endpoint
        $responseVat = $this->get('/reports/vat?start_date=' . date('Y-01-01') . '&end_date=' . date('Y-m-d'));
        $responseVat->assertStatus(200);

        // Test Profit & Loss endpoint
        $responsePl = $this->get('/reports/profit-loss?start_date=' . date('Y-01-01') . '&end_date=' . date('Y-m-d'));
        $responsePl->assertStatus(200);
    }
}
