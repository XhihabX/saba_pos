<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\MfsTransaction;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\RegisterShift;
use App\Models\Stock;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use App\Services\EfdBridgeService;
use App\Services\SmsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class EnterpriseComplianceIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_enterprise_compliance_mfs_fefo_baki_and_z_report_integration(): void
    {
        $this->withoutMiddleware();

        // 1. Setup Tenant, Store with BIN, & User
        $tenant = Tenant::create([
            'name' => 'Metro Retail Group',
            'code' => 'TENANT-METRO',
            'email' => 'metro@iotpos.com',
            'status' => 'active',
            'subscription_status' => 'active',
        ]);

        $store = Store::create([
            'tenant_id' => $tenant->id,
            'name' => 'Dhanmondi Superstore',
            'code' => 'STORE-DHAKA-01',
            'bin_number' => '123456789-0101',
            'mfs_number' => '01700000000',
            'default_tax_rate' => 5.00,
            'is_godown' => false,
        ]);

        $cashier = User::create([
            'tenant_id' => $tenant->id,
            'store_id' => $store->id,
            'name' => 'Karem Rahaman',
            'email' => 'cashier@metro.com',
            'password' => Hash::make('password'),
            'role' => 'cashier',
            'pos_pin' => '1234',
        ]);

        $this->actingAs($cashier);

        // 2. Open Cashier Register Shift
        $shift = RegisterShift::create([
            'tenant_id' => $tenant->id,
            'store_id' => $store->id,
            'user_id' => $cashier->id,
            'opening_cash' => 2000.00,
            'status' => 'open',
            'opened_at' => now(),
        ]);

        // 3. Test MFS Webhook Listener Endpoint
        $mfsPayload = [
            'trx_id' => '9K87J6H5G4',
            'sender' => '01811223344',
            'amount' => 1500.00,
            'gateway' => 'bkash',
        ];

        $mfsResponse = $this->postJson('/api/v1/mfs-webhook', $mfsPayload);
        $mfsResponse->assertStatus(201);
        $mfsResponse->assertJson(['success' => true]);

        $mfsRecord = MfsTransaction::where('trx_id', '9K87J6H5G4')->first();
        $this->assertNotNull($mfsRecord);
        $this->assertEquals('unclaimed', $mfsRecord->status);

        // Test duplicate MFS TrxID prevention
        $dupMfsResponse = $this->postJson('/api/v1/mfs-webhook', $mfsPayload);
        $dupMfsResponse->assertStatus(200);

        // 4. Test Customer Credit Limit Guard (Baki Khata)
        $customer = Customer::create([
            'tenant_id' => $tenant->id,
            'name' => 'Arefin Chowdhury',
            'phone' => '01711223344',
            'due_balance' => 4500.00,
            'credit_limit' => 5000.00, // Limit is 5000, current due is 4500
        ]);

        $product = Product::create([
            'tenant_id' => $tenant->id,
            'name' => 'Premium Basmati Rice 5kg',
            'sku' => 'RICE-BASMATI-5K',
            'purchase_cost' => 400.00,
            'selling_price' => 600.00,
        ]);

        Stock::create([
            'store_id' => $store->id,
            'product_id' => $product->id,
            'quantity' => 100.00,
        ]);

        // Create 2 Product Batches for FEFO Testing
        $expiringBatch = ProductBatch::create([
            'tenant_id' => $tenant->id,
            'store_id' => $store->id,
            'product_id' => $product->id,
            'batch_no' => 'BATCH-EARLY-2026',
            'expiry_date' => now()->addDays(10),
            'quantity' => 2.00,
        ]);

        $laterBatch = ProductBatch::create([
            'tenant_id' => $tenant->id,
            'store_id' => $store->id,
            'product_id' => $product->id,
            'batch_no' => 'BATCH-LATER-2027',
            'expiry_date' => now()->addYear(),
            'quantity' => 50.00,
        ]);

        // Attempt Customer Credit Sale exceeding limit (Due: 4500 + New Due: 1000 = 5500 > 5000 limit)
        $excessCreditPayload = [
            'store_id' => $store->id,
            'customer_id' => $customer->id,
            'payment_method' => 'credit',
            'paid_amount' => 0.00,
            'discount_amount' => 0.00,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2, 'discount' => 0],
            ],
        ];

        $exceedResponse = $this->postJson('/pos/checkout', $excessCreditPayload);
        $exceedResponse->assertStatus(422);

        // 5. Test Successful POS Checkout with FEFO Batch Deduction & MFS TrxID Claiming
        $checkoutPayload = [
            'store_id' => $store->id,
            'customer_id' => $customer->id,
            'payment_method' => 'mobile_wallet',
            'paid_amount' => 1260.00,
            'discount_amount' => 0.00,
            'client_uuid' => 'OFFLINE-UUID-99887766',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2, 'discount' => 0],
            ],
            'payments' => [
                ['method' => 'mobile_wallet', 'amount' => 1260.00, 'reference_no' => '9K87J6H5G4'],
            ],
        ];

        $checkoutResponse = $this->postJson('/pos/checkout', $checkoutPayload);
        $checkoutResponse->assertStatus(200);
        $checkoutResponse->assertJson(['success' => true]);

        // Verify Order created with Idempotency Key
        $order = Order::where('invoice_no', $checkoutResponse->json('invoice_no'))->first();
        $this->assertNotNull($order);
        $this->assertEquals(1260.00, $order->grand_total);

        // Verify FEFO Batch Stock deduction (earliest batch should be deducted first)
        $expiringBatch->refresh();
        $this->assertEquals(0.00, $expiringBatch->quantity, 'Earliest batch quantity must be deducted first via FEFO');

        // Verify MFS Transaction Status updated to 'claimed'
        $mfsRecord->refresh();
        $this->assertEquals('claimed', $mfsRecord->status);
        $this->assertEquals($order->id, $mfsRecord->order_id);

        // Test Idempotency Retry Protection (Submitting same client_uuid should return idempotent success without duplicate order)
        $idempotentResponse = $this->postJson('/pos/checkout', $checkoutPayload);
        $idempotentResponse->assertStatus(200);
        $idempotentResponse->assertJson(['message' => 'Order already processed (idempotent)']);

        // 6. Test EFD Bridge Statutory Payload Generator
        $efdPayload = EfdBridgeService::generatePayload($order);
        $this->assertEquals('123456789-0101', $efdPayload['bin']);
        $this->assertEquals('READY_FOR_SDC', $efdPayload['efd_status']);

        // 7. Test SMS Reminder Dispatch Service
        $smsSent = SmsService::sendDueReminder($store->id, $customer->name, $customer->phone, $customer->due_balance);
        $this->assertTrue($smsSent, 'SMS service must dispatch or log due payment reminder');

        // 8. Test Day-End Z-Report Endpoint
        $zResponse = $this->get("/manager/shifts/{$shift->id}/z-report");
        $zResponse->assertStatus(200);

        // 9. Test NBR VAT Report & Stock Valuation Report Endpoints
        $vatResponse = $this->get('/reports/vat');
        $vatResponse->assertStatus(200);

        $stockReportResponse = $this->get('/reports/stock');
        $stockReportResponse->assertStatus(200);
    }
}
