<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Stock;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BranchReportsTest extends TestCase
{
    use RefreshDatabase;

    protected $merchant;
    protected $tenant;
    protected $branchA;
    protected $branchB;
    protected $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Multi-Branch Merchant',
            'code' => 'TEN-BRANCH-01',
            'email' => 'branchtenant@example.com',
            'subscription_status' => 'active',
            'expires_at' => now()->addYear(),
        ]);



        $this->merchant = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Branch Executive',
            'email' => 'branchexec@example.com',
            'password' => bcrypt('secret123'),
            'role' => 'merchant',
            'is_approved' => true,
        ]);

        $this->branchA = Store::create([
            'tenant_id' => $this->tenant->id,
            'code' => 'BR-A',
            'name' => 'Dhanmondi Branch',
            'bin_number' => '1111222233',
        ]);

        $this->branchB = Store::create([
            'tenant_id' => $this->tenant->id,
            'code' => 'BR-B',
            'name' => 'Gulshan Branch',
            'bin_number' => '4444555566',
        ]);


        $this->product = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Branch Test Product',
            'sku' => 'BRANCH-PROD',
            'purchase_cost' => 100.00,
            'selling_price' => 200.00,
        ]);

        Stock::create([
            'tenant_id' => $this->tenant->id,
            'store_id' => $this->branchA->id,
            'product_id' => $this->product->id,
            'quantity' => 100,
        ]);

        Stock::create([
            'tenant_id' => $this->tenant->id,
            'store_id' => $this->branchB->id,
            'product_id' => $this->product->id,
            'quantity' => 50,
        ]);

        // Branch A sales: 2 orders (Total Gross: 400.00)
        $orderA1 = Order::create([
            'tenant_id' => $this->tenant->id,
            'store_id' => $this->branchA->id,
            'user_id' => $this->merchant->id,
            'invoice_no' => 'INV-BRANCH-A1',
            'subtotal' => 200.00,
            'grand_total' => 200.00,
            'tax_amount' => 0.00,
            'paid_amount' => 200.00,
            'payment_status' => 'paid',
            'created_at' => now(),
        ]);
        OrderItem::create([
            'tenant_id' => $this->tenant->id,
            'order_id' => $orderA1->id,
            'product_id' => $this->product->id,
            'product_name' => $this->product->name,
            'quantity' => 1,
            'unit_price' => 200.00,
            'cost_price' => 100.00,
            'total' => 200.00,
        ]);

        // Branch B sales: 1 order (Total Gross: 200.00)
        $orderB1 = Order::create([
            'tenant_id' => $this->tenant->id,
            'store_id' => $this->branchB->id,
            'user_id' => $this->merchant->id,
            'invoice_no' => 'INV-BRANCH-B1',
            'subtotal' => 200.00,
            'grand_total' => 200.00,
            'tax_amount' => 0.00,
            'paid_amount' => 200.00,
            'payment_status' => 'paid',
            'created_at' => now(),
        ]);
        OrderItem::create([
            'tenant_id' => $this->tenant->id,
            'order_id' => $orderB1->id,
            'product_id' => $this->product->id,
            'product_name' => $this->product->name,
            'quantity' => 1,
            'unit_price' => 200.00,
            'cost_price' => 100.00,
            'total' => 200.00,
        ]);
    }

    public function test_per_branch_stock_and_sales_report_aggregation()
    {
        $response = $this->actingAs($this->merchant)->getJson('/reports/branch');

        $response->assertStatus(200);
        $data = $response->json('branch_data');

        $this->assertCount(2, $data);

        $dhanmondi = collect($data)->firstWhere('store_name', 'Dhanmondi Branch');
        $gulshan = collect($data)->firstWhere('store_name', 'Gulshan Branch');

        $this->assertEquals(1, $dhanmondi['total_orders']);
        $this->assertEquals(200.00, $dhanmondi['gross_revenue']);
        $this->assertEquals(100, $dhanmondi['total_stock_qty']);

        $this->assertEquals(1, $gulshan['total_orders']);
        $this->assertEquals(200.00, $gulshan['gross_revenue']);
        $this->assertEquals(50, $gulshan['total_stock_qty']);
    }
}
