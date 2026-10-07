<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\RegisterShift;
use App\Models\Stock;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductVariantTest extends TestCase
{
    use RefreshDatabase;

    protected $merchant;
    protected $tenant;
    protected $store;
    protected $product;
    protected $variantRed;
    protected $variantBlue;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Variant Merchant',
            'code' => 'TEN-VAR-01',
            'email' => 'varianttenant@example.com',
            'subscription_status' => 'active',
            'expires_at' => now()->addYear(),
        ]);



        $this->merchant = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Variant Cashier',
            'email' => 'variant@example.com',
            'password' => bcrypt('secret123'),
            'role' => 'merchant',
            'is_approved' => true,
        ]);

        $this->store = Store::create([
            'tenant_id' => $this->tenant->id,
            'code' => 'BR-VAR',
            'name' => 'Variant Store',
            'bin_number' => '123456789',
            'is_vat_registered' => true,
            'allow_negative_stock' => false,
        ]);


        $this->product = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Cotton Polo T-Shirt',
            'sku' => 'POLO-BASE',
            'purchase_cost' => 300.00,
            'selling_price' => 500.00,
            'is_active' => true,
        ]);

        $this->variantRed = ProductVariant::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'name' => 'Red / Large',
            'sku' => 'POLO-RED-L',
            'barcode' => '890123456701',
            'price' => 550.00,
            'cost_price' => 320.00,
            'attributes' => ['color' => 'Red', 'size' => 'L'],
        ]);

        $this->variantBlue = ProductVariant::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'name' => 'Blue / Small',
            'sku' => 'POLO-BLUE-S',
            'barcode' => '890123456702',
            'price' => 500.00,
            'cost_price' => 300.00,
            'attributes' => ['color' => 'Blue', 'size' => 'S'],
        ]);

        Stock::create([
            'tenant_id' => $this->tenant->id,
            'store_id' => $this->store->id,
            'product_id' => $this->product->id,
            'variant_id' => $this->variantRed->id,
            'quantity' => 20,
        ]);

        Stock::create([
            'tenant_id' => $this->tenant->id,
            'store_id' => $this->store->id,
            'product_id' => $this->product->id,
            'variant_id' => $this->variantBlue->id,
            'quantity' => 15,
        ]);

        RegisterShift::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->merchant->id,
            'store_id' => $this->store->id,
            'opening_balance' => 1000.00,
            'status' => 'open',
            'opened_at' => now(),
        ]);
    }

    public function test_variant_creation_and_pos_checkout_stock_deduction()
    {
        $payload = [
            'store_id' => $this->store->id,
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'variant_id' => $this->variantRed->id,
                    'quantity' => 3,
                    'discount' => 0,
                ]
            ],
            'paid_amount' => 1897.50, // 3 * 550 = 1650 + 15% VAT (247.50)
            'payment_method' => 'cash',
        ];

        $response = $this->actingAs($this->merchant)->postJson('/pos/checkout', $payload);

        $response->assertStatus(200);

        // Verify stock for Red variant was decremented by 3 (from 20 to 17)
        $this->assertDatabaseHas('stocks', [
            'store_id' => $this->store->id,
            'product_id' => $this->product->id,
            'variant_id' => $this->variantRed->id,
            'quantity' => 17,
        ]);

        // Verify Blue variant stock remains unchanged (15)
        $this->assertDatabaseHas('stocks', [
            'store_id' => $this->store->id,
            'product_id' => $this->product->id,
            'variant_id' => $this->variantBlue->id,
            'quantity' => 15,
        ]);

        // Verify OrderItem stores variant metadata
        $this->assertDatabaseHas('order_items', [
            'product_id' => $this->product->id,
            'variant_id' => $this->variantRed->id,
            'variant_name' => 'Red / Large',
            'quantity' => 3,
            'unit_price' => 550.00,
        ]);
    }
}
