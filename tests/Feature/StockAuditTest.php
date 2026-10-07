<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Stock;
use App\Models\StockAudit;
use App\Models\StockAuditItem;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockAuditTest extends TestCase
{
    use RefreshDatabase;

    protected $merchant;
    protected $tenant;
    protected $store;
    protected $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Stock Audit Tenant',
            'code' => 'TEN-AUDIT-01',
            'email' => 'audittenant@example.com',
            'subscription_status' => 'active',
            'expires_at' => now()->addYear(),
        ]);



        $this->merchant = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Store Auditor',
            'email' => 'auditor@example.com',
            'password' => bcrypt('secret123'),
            'role' => 'merchant',
            'is_approved' => true,
        ]);

        $this->store = Store::create([
            'tenant_id' => $this->tenant->id,
            'code' => 'BR-AUDIT',
            'name' => 'Audit Outlet Store',
        ]);


        $this->product = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Inventory Audit Item',
            'sku' => 'AUDIT-001',
            'purchase_cost' => 100.00,
            'selling_price' => 150.00,
        ]);

        Stock::create([
            'tenant_id' => $this->tenant->id,
            'store_id' => $this->store->id,
            'product_id' => $this->product->id,
            'quantity' => 50,
        ]);
    }

    public function test_full_stock_audit_and_variance_adjustment_workflow()
    {
        // 1. Start a stock count session
        $startResponse = $this->actingAs($this->merchant)->postJson('/stock-audits', [
            'store_id' => $this->store->id,
            'notes' => 'Q4 Physical Stock Count',
        ]);

        $startResponse->assertStatus(201);
        $auditId = $startResponse->json('audit.id');

        $this->assertDatabaseHas('stock_audits', [
            'id' => $auditId,
            'status' => 'draft',
            'store_id' => $this->store->id,
        ]);

        $auditItem = StockAuditItem::where('stock_audit_id', $auditId)->first();
        $this->assertEquals(50, $auditItem->expected_qty);

        // 2. Input physical counted quantities (e.g. 45 items found, 5 missing => variance -5)
        $updateResponse = $this->actingAs($this->merchant)->postJson("/stock-audits/{$auditId}/items", [
            'items' => [
                [
                    'id' => $auditItem->id,
                    'counted_qty' => 45,
                ]
            ]
        ]);

        $updateResponse->assertStatus(200);

        $this->assertDatabaseHas('stock_audit_items', [
            'id' => $auditItem->id,
            'counted_qty' => 45,
            'variance_qty' => -5,
            'variance_value' => -500.00, // -5 * 100 unit cost
        ]);

        // 3. Approve stock adjustment
        $approveResponse = $this->actingAs($this->merchant)->postJson("/stock-audits/{$auditId}/approve");

        $approveResponse->assertStatus(200);

        $this->assertDatabaseHas('stock_audits', [
            'id' => $auditId,
            'status' => 'approved',
        ]);

        // Verify stock updated from 50 to 45
        $this->assertDatabaseHas('stocks', [
            'store_id' => $this->store->id,
            'product_id' => $this->product->id,
            'quantity' => 45,
        ]);

        // Verify AuditLog entry
        $this->assertDatabaseHas('audit_logs', [
            'tenant_id' => $this->tenant->id,
            'action' => 'stock_audit_approved',
        ]);
    }
}
