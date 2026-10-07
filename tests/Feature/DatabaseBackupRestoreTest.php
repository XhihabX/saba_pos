<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class DatabaseBackupRestoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_automated_database_backup_and_real_restore()
    {
        $testDbFile = database_path('database.sqlite');
        if (!File::exists($testDbFile)) {
            File::put($testDbFile, '');
        }

        // Configure test database file for SQLite backup/restore command verification
        config(['database.connections.sqlite.database' => $testDbFile]);

        // 1. Create a distinctive test record
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

        // Copy current memory database content into file for backup command
        $memorySql = "-- SQLite Snapshot\n";
        $pdo = DB::connection()->getPdo();
        $tables = DB::select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");
        $memorySql .= "PRAGMA foreign_keys = OFF;\n";
        foreach ($tables as $t) {
            $createRow = DB::selectOne("SELECT sql FROM sqlite_master WHERE type='table' AND name = ?", [$t->name]);
            if ($createRow && $createRow->sql) {
                $memorySql .= "DROP TABLE IF EXISTS \"{$t->name}\";\n";
                $memorySql .= $createRow->sql . ";\n";
            }
            $rows = DB::table($t->name)->get();
            foreach ($rows as $row) {
                $cols = array_keys((array)$row);
                $vals = array_map(fn($v) => is_null($v) ? 'NULL' : $pdo->quote($v), array_values((array)$row));
                $memorySql .= "INSERT INTO \"{$t->name}\" (\"" . implode('", "', $cols) . "\") VALUES (" . implode(', ', $vals) . ");\n";
            }
        }
        $memorySql .= "PRAGMA foreign_keys = ON;\n";

        $dbFilePdo = new \PDO("sqlite:" . $testDbFile);
        $dbFilePdo->exec($memorySql);

        // 2. Execute backup command
        $exitCodeBackup = Artisan::call('pos:backup', ['--keep' => 30]);
        $this->assertEquals(0, $exitCodeBackup);

        $backupDir = storage_path('app/backups');
        $files = File::files($backupDir);
        $this->assertNotEmpty($files, 'Backup file should be created in storage/app/backups/');

        // Find the newest backup file
        $latestBackup = collect($files)->sortByDesc(fn($f) => $f->getMTime())->first();
        $filename = $latestBackup->getFilename();

        // 3. Wipe/delete user to simulate data corruption/loss
        $user->forceDelete();
        $this->assertDatabaseMissing('users', ['email' => 'backuprestored@example.com']);

        // 4. Run restore command using the generated backup archive
        $exitCodeRestore = Artisan::call('pos:restore', ['filename' => $filename]);
        $this->assertEquals(0, $exitCodeRestore);

        // Restore file database contents into test PDO
        $restoredDbPdo = new \PDO("sqlite:" . $testDbFile);
        $stmt = $restoredDbPdo->query("SELECT count(*) FROM users WHERE email = 'backuprestored@example.com'");
        $count = $stmt->fetchColumn();

        $this->assertEquals(1, $count, 'Restored database file must contain the backup user record.');
    }
}
