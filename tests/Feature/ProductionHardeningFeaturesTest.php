<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ProductionHardeningFeaturesTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('services.mfs.secret_key', 'test_hmac_secret_key_12345');
        $this->tenant = Tenant::create([
            'name' => 'Demo Tenant',
            'code' => 'DEMO',
            'email' => 'tenant@example.com',
            'phone' => '01700000000',
            'subscription_status' => 'active',
            'expires_at' => now()->addDays(30),
        ]);
    }

    public function test_hmac_webhook_accepts_valid_signature_and_logs_mfs_transaction()
    {
        $timestamp = time();
        $trxId = 'TRX12345678';
        $amount = '500.00';
        $secret = 'test_hmac_secret_key_12345';
        $signature = hash_hmac('sha256', "{$timestamp}.{$trxId}.{$amount}", $secret);

        $response = $this->postJson('/api/v1/mfs-webhook', [
            'trx_id' => $trxId,
            'amount' => $amount,
            'sender' => '01700000000',
            'gateway' => 'bkash',
        ], [
            'HTTP_X_MFS_SIGNATURE' => $signature,
            'HTTP_X_MFS_TIMESTAMP' => (string) $timestamp,
        ]);

        $response->assertStatus(201)
                 ->assertJson(['success' => true]);

        $this->assertDatabaseHas('mfs_transactions', [
            'trx_id' => $trxId,
            'amount' => 500.00,
            'gateway' => 'bkash',
        ]);
    }

    public function test_hmac_webhook_rejects_invalid_signature()
    {
        $timestamp = time();
        $trxId = 'TRX99999999';
        $amount = '100.00';

        $response = $this->postJson('/api/v1/mfs-webhook', [
            'trx_id' => $trxId,
            'amount' => $amount,
            'sender' => '01700000000',
            'gateway' => 'bkash',
        ], [
            'HTTP_X_MFS_SIGNATURE' => 'invalid_signature_hash_string',
            'HTTP_X_MFS_TIMESTAMP' => (string) $timestamp,
        ]);

        $response->assertStatus(401)
                 ->assertJson(['success' => false]);
    }

    public function test_hmac_webhook_secret_key_fallback_authenticates_successfully()
    {
        $response = $this->postJson('/api/v1/mfs-webhook', [
            'trx_id' => 'TRX_SECRET_KEY_TEST',
            'amount' => '250.00',
            'sender' => '01800000000',
            'gateway' => 'nagad',
            'timestamp' => time(),
            'secret_key' => 'test_hmac_secret_key_12345',
        ]);

        $response->assertStatus(201)
                 ->assertJson(['success' => true]);
    }

    public function test_printable_pdf_invoice_route_renders_successfully()
    {
        $user = User::factory()->create(['role' => 'merchant', 'tenant_id' => $this->tenant->id]);
        $store = Store::create(['tenant_id' => $this->tenant->id, 'code' => 'ST01', 'name' => 'Test Store', 'phone' => '01700000000', 'address' => 'Dhaka']);
        $order = Order::create([
            'tenant_id' => $this->tenant->id,
            'store_id' => $store->id,
            'invoice_no' => 'INV-1001',
            'subtotal' => 1000.00,
            'grand_total' => 1150.00,
            'tax_amount' => 150.00,
            'discount_amount' => 0.00,
            'paid_amount' => 1150.00,
            'payment_status' => 'paid',
            'payment_method' => 'cash',
        ]);

        $response = $this->actingAs($user)->get("/pos/invoice/{$order->id}/pdf");

        $response->assertStatus(200)
                 ->assertSee('INV-1001')
                 ->assertSee('Invoice');
    }

    public function test_official_mushak_63_tax_invoice_renders_with_nbr_compliance()
    {
        $user = User::factory()->create(['role' => 'merchant', 'tenant_id' => $this->tenant->id]);
        $store = Store::create(['tenant_id' => $this->tenant->id, 'code' => 'ST02', 'name' => 'Test Store VAT', 'phone' => '01700000000', 'address' => 'Dhaka', 'default_tax_rate' => 15.0]);
        $product = Product::create(['tenant_id' => $this->tenant->id, 'name' => 'Item 1', 'sku' => 'SKU-001', 'unit_price' => 1000.00, 'code' => 'P1', 'is_active' => true]);
        
        $order = Order::create([
            'tenant_id' => $this->tenant->id,
            'store_id' => $store->id,
            'invoice_no' => 'INV-VAT-1002',
            'subtotal' => 1000.00,
            'grand_total' => 1150.00,
            'tax_amount' => 150.00,
            'discount_amount' => 0.00,
            'paid_amount' => 1150.00,
            'payment_status' => 'paid',
            'payment_method' => 'cash',
        ]);

        OrderItem::create([
            'tenant_id' => $this->tenant->id,
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => 'Item 1',
            'quantity' => 1,
            'unit_price' => 1000.00,
            'vat_rate' => 15.00,
            'vat_amount' => 150.00,
            'total' => 1150.00,
        ]);

        $response = $this->actingAs($user)->get("/vat/mushak-6.3/{$order->id}");

        $response->assertStatus(200)
                 ->assertSee('মুসক-৬.৩')
                 ->assertSee('INV-VAT-1002')
                 ->assertSee('15.0%');
    }

    public function test_sales_report_csv_export_generates_downloadable_file()
    {
        $user = User::factory()->create(['role' => 'merchant', 'tenant_id' => $this->tenant->id]);
        $store = Store::create(['tenant_id' => $this->tenant->id, 'code' => 'ST03', 'name' => 'Test Store CSV', 'phone' => '01700000000', 'address' => 'Dhaka']);
        Order::create([
            'tenant_id' => $this->tenant->id,
            'store_id' => $store->id,
            'invoice_no' => 'INV-CSV-1',
            'subtotal' => 475.00,
            'grand_total' => 500.00,
            'tax_amount' => 25.00,
            'discount_amount' => 0.00,
            'paid_amount' => 500.00,
            'payment_status' => 'paid',
            'payment_method' => 'cash',
        ]);

        $response = $this->actingAs($user)->get('/reports/sales/export-csv');

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        
        ob_start();
        $response->sendContent();
        $content = ob_get_clean();

        $this->assertStringContainsString('INV-CSV-1', $content);
    }

    #[\PHPUnit\Framework\Attributes\Group('backup')]
    public function test_database_backup_and_restore_commands_execute_successfully()
    {
        $backupDir = storage_path('app/backups');
        if (File::exists($backupDir)) {
            File::cleanDirectory($backupDir);
        } else {
            File::makeDirectory($backupDir, 0755, true);
        }

        $exitCodeBackup = Artisan::call('pos:backup');
        $this->assertEquals(0, $exitCodeBackup);

        $backupFiles = File::files($backupDir);
        $this->assertNotEmpty($backupFiles, 'Backup file should exist in storage/app/backups');

        $latestBackup = collect($backupFiles)->sortByDesc(fn($f) => $f->getMTime())->first()->getFilename();

        $exitCodeRestore = Artisan::call('pos:restore', [
            'filename' => $latestBackup,
            '--i-understand-this-overwrites-data' => true,
        ]);
        $this->assertEquals(0, $exitCodeRestore);
    }
}
