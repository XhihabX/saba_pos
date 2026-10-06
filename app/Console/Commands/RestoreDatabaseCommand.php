<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class RestoreDatabaseCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pos:restore {filename : The backup filename in storage/app/backups/}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Restore MySQL/SQLite Database from backup file';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $filename = $this->argument('filename');
        $backupPath = storage_path("app/backups/{$filename}");

        if (!File::exists($backupPath)) {
            $this->error("❌ Backup file not found at: {$backupPath}");
            return Command::FAILURE;
        }

        $this->info("Starting database restore from: {$filename}");
        $driver = config('database.default', 'mysql');

        if ($driver === 'sqlite') {
            $dbPath = config('database.connections.sqlite.database');
            File::copy($backupPath, $dbPath);
            $this->info("✅ SQLite database successfully restored from {$filename}!");
            Log::info("SQLite database restored from {$filename}");
            return Command::SUCCESS;
        }

        // MySQL Restore
        $dbName = config('database.connections.mysql.database');
        $dbUser = config('database.connections.mysql.username');
        $dbPass = config('database.connections.mysql.password');
        $dbHost = config('database.connections.mysql.host', '127.0.0.1');
        $dbPort = config('database.connections.mysql.port', '3306');

        if (str_ends_with($filename, '.gz')) {
            $cmd = "gunzip -c " . escapeshellarg($backupPath) . " | mysql --host={$dbHost} --port={$dbPort} --user={$dbUser} " . (!empty($dbPass) ? "--password=" . escapeshellarg($dbPass) : "") . " {$dbName}";
        } else {
            $cmd = "mysql --host={$dbHost} --port={$dbPort} --user={$dbUser} " . (!empty($dbPass) ? "--password=" . escapeshellarg($dbPass) : "") . " {$dbName} < " . escapeshellarg($backupPath);
        }

        exec($cmd, $output, $exitCode);

        if ($exitCode === 0) {
            $this->info("✅ MySQL database successfully restored from {$filename}!");
            Log::info("MySQL database restored from {$filename}");
            return Command::SUCCESS;
        }

        $this->error("❌ MySQL restore failed with exit code {$exitCode}.");
        return Command::FAILURE;
    }
}
