<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use PHPUnit\Framework\Attributes\Group;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

#[Group('backup')]
class DatabaseBackupRestoreTest extends TestCase
{
    use DatabaseMigrations;

    public function test_automated_database_backup_and_real_restore()
    {
        // 1. Ensure backup directory is clean before running test
        $backupDir = storage_path('app/backups');
        if (File::exists($backupDir)) {
            File::cleanDirectory($backupDir);
        } else {
            File::makeDirectory($backupDir, 0755, true);
        }

        // 2. Create a distinctive test record
        $tenant = Tenant::create([
            'name' => 'Backup Restoration Merchant Test',
            'code' => 'TEN-BACKUP-01',
            'email' => 'backuptenant@example.com',
            'subscription_status' => 'active',
            'expires_at' => now()->addYear(),
        ]);

        $user = User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Backup Test User',
            'email' => 'backuprestored@example.com',
            'password' => bcrypt('secret123'),
            'role' => 'merchant',
            'is_approved' => true,
        ]);

        $this->assertDatabaseHas('users', ['email' => 'backuprestored@example.com']);

        // 3. Execute backup command
        $exitCodeBackup = Artisan::call('pos:backup', ['--keep' => 30]);
        $this->assertEquals(0, $exitCodeBackup);

        $files = File::files($backupDir);
        $this->assertNotEmpty($files, 'Backup file should be created in storage/app/backups/');

        // Find the newest backup file created by this test
        $latestBackup = collect($files)->sortByDesc(fn($f) => $f->getMTime())->first();
        $filename = $latestBackup->getFilename();

        // 4. Wipe/delete user to simulate data loss
        $user->forceDelete();
        $this->assertDatabaseMissing('users', ['email' => 'backuprestored@example.com']);

        // 5. Run restore command using the generated backup archive
        $exitCodeRestore = Artisan::call('pos:restore', ['filename' => $filename]);
        $this->assertEquals(0, $exitCodeRestore);

        // 6. Verify restored record exists in database
        $this->assertDatabaseHas('users', ['email' => 'backuprestored@example.com']);
    }
}

