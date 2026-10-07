<?php

namespace Tests\Feature;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class MfsWebhookHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_mfs_webhook_rejects_with_503_when_no_secret_is_configured(): void
    {
        // Ensure env/config secret is empty
        Config::set('services.mfs.secret_key', null);

        $tenant = Tenant::create([
            'name' => 'No Secret Tenant',
            'code' => 'no-secret-tenant',
            'email' => 'nosecret@example.com',
            'status' => 'active',
            'mfs_webhook_secret' => null,
        ]);

        $response = $this->postJson('/api/v1/mfs-webhook', [
            'trx_id' => 'TRX_NO_SECRET',
            'amount' => '100.00',
            'timestamp' => time(),
        ], [
            'X-MFS-Tenant-ID' => (string) $tenant->id,
        ]);

        $response->assertStatus(503)
            ->assertJson([
                'success' => false,
                'message' => 'MFS Webhook service is unconfigured. Secret key required.',
            ]);
    }

    public function test_mfs_webhook_enforces_timestamp_check_in_all_environments(): void
    {
        Config::set('services.mfs.secret_key', 'test_secret_key_999');

        Tenant::create([
            'name' => 'Default MFS Tenant',
            'code' => 'default-mfs-tenant',
            'email' => 'defaultmfs@example.com',
            'status' => 'active',
        ]);

        // Missing timestamp -> 403
        $resNoTs = $this->postJson('/api/v1/mfs-webhook', [
            'trx_id' => 'TRX_NO_TS',
            'amount' => '100.00',
            'secret' => 'test_secret_key_999',
        ]);
        $resNoTs->assertStatus(403)
            ->assertJson(['success' => false]);

        // Stale timestamp (older than 300 seconds) -> 403
        $staleTs = time() - 360;
        $resStale = $this->postJson('/api/v1/mfs-webhook', [
            'trx_id' => 'TRX_STALE_TS',
            'amount' => '100.00',
            'secret' => 'test_secret_key_999',
            'timestamp' => $staleTs,
        ]);
        $resStale->assertStatus(403)
            ->assertJson(['success' => false]);

        // Valid timestamp -> 201
        $currentTs = time();
        $resValid = $this->postJson('/api/v1/mfs-webhook', [
            'trx_id' => 'TRX_VALID_TS',
            'amount' => '100.00',
            'secret' => 'test_secret_key_999',
            'timestamp' => $currentTs,
        ]);
        $resValid->assertStatus(201)
            ->assertJson(['success' => true]);
    }

    public function test_mfs_webhook_validates_hmac_signature_with_real_secret(): void
    {
        $secret = 'super_secure_webhook_secret_key';
        Config::set('services.mfs.secret_key', $secret);

        $tenant = Tenant::create([
            'name' => 'HMAC Tenant',
            'code' => 'hmac-tenant',
            'email' => 'hmac@example.com',
            'status' => 'active',
        ]);

        $timestamp = time();
        $trxId = 'TRX_HMAC_VALID_001';
        $amount = '500.00';

        $signature = hash_hmac('sha256', "{$timestamp}.{$trxId}.{$amount}", $secret);

        $response = $this->postJson('/api/v1/mfs-webhook', [
            'trx_id' => $trxId,
            'amount' => $amount,
            'sender' => '01711112222',
            'gateway' => 'bkash',
        ], [
            'HTTP_X_MFS_SIGNATURE' => $signature,
            'HTTP_X_MFS_TIMESTAMP' => (string) $timestamp,
            'HTTP_X_MFS_TENANT_ID' => (string) $tenant->id,
        ]);

        $response->assertStatus(201)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('mfs_transactions', [
            'trx_id' => $trxId,
            'amount' => 500.00,
            'status' => 'unclaimed',
        ]);
    }
}
