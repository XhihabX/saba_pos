<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PdfExportTest extends TestCase
{
    use RefreshDatabase;

    protected $merchant;
    protected $tenant;
    protected $store;
    protected $order;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'PDF Test Merchant',
            'code' => 'TEN-PDF-01',
            'email' => 'pdftenant@example.com',
            'subscription_status' => 'active',
            'expires_at' => now()->addYear(),
        ]);



        $this->merchant = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'PDF Admin',
            'email' => 'pdfadmin@example.com',
            'password' => bcrypt('secret123'),
            'role' => 'merchant',
            'is_approved' => true,
        ]);

        $this->store = Store::create([
            'tenant_id' => $this->tenant->id,
            'code' => 'BR-PDF',
            'name' => 'PDF Outlet',
            'bin_number' => '9988776655',
            'is_vat_registered' => true,
            'default_tax_rate' => 15.0,
        ]);


        $product = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'PDF Sample Product',
            'sku' => 'PDF-SKU-001',
            'purchase_cost' => 100.00,
            'selling_price' => 150.00,
            'vat_rate' => 15.0,
            'is_active' => true,
        ]);

        $this->order = Order::create([
            'tenant_id' => $this->tenant->id,
            'store_id' => $this->store->id,
            'user_id' => $this->merchant->id,
            'invoice_no' => 'INV-PDF-1001',
            'subtotal' => 150.00,
            'discount_amount' => 0.00,
            'tax_amount' => 22.50,
            'grand_total' => 172.50,
            'paid_amount' => 172.50,
            'payment_status' => 'paid',
            'payment_method' => 'cash',
        ]);

        OrderItem::create([
            'tenant_id' => $this->tenant->id,
            'order_id' => $this->order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => 1,
            'unit_price' => 150.00,
            'cost_price' => 100.00,
            'discount' => 0.00,
            'vat_rate' => 15.0,
            'vat_amount' => 22.50,
            'total' => 150.00,
        ]);
    }

    public function test_sales_invoice_pdf_generation()
    {
        $response = $this->actingAs($this->merchant)->get("/invoices/{$this->order->id}/pdf");

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }

    public function test_mushak_63_tax_invoice_pdf_generation()
    {
        $response = $this->actingAs($this->merchant)->get("/invoices/{$this->order->id}/mushak63/pdf");

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }

    public function test_branch_report_pdf_generation()
    {
        $response = $this->actingAs($this->merchant)->get("/reports/branch/pdf");

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }
}
