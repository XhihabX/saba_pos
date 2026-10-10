<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class RestoreDatabaseCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pos:restore {filename : Backup filename in storage/app/backups/} {--force : Force restore without prompt} {--i-understand-this-overwrites-data : Allow restoring non-test database}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Restore Database from backup archive (.sql, .sqlite, .gz)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $filename = $this->argument('filename');
        $backupPath = storage_path("app/backups/{$filename}");

        if (! File::exists($backupPath)) {
            $this->error("❌ Backup file not found at: {$backupPath}");

            return Command::FAILURE;
        }

        $driver = config('database.default', 'sqlite');
        $dbName = config("database.connections.{$driver}.database");
        $dbBaseName = pathinfo((string) $dbName, PATHINFO_FILENAME);
        $isTestDb = (bool) preg_match('/(_benchmark|_test)$/i', $dbBaseName) || $dbName === ':memory:';

        if (! $isTestDb && ! $this->option('i-understand-this-overwrites-data')) {
            $this->error("\n[SAFETY ERROR] Database restoration safety violation!");
            $this->error("Active target database \"{$dbName}\" is not named like *_benchmark or *_test.");
            $this->error('Refusing to restore database to protect real/production data!');
            $this->error("Pass --i-understand-this-overwrites-data to override safety guard.\n");

            return Command::FAILURE;
        }

        $this->info("Starting database restore from: {$filename}");

        if ($driver === 'sqlite') {
            $dbPath = config('database.connections.sqlite.database');
            if ($dbPath === ':memory:' || ! File::exists($dbPath)) {
                $dbPath = database_path('database.sqlite');
            }

            if (! File::exists(dirname($dbPath))) {
                File::makeDirectory(dirname($dbPath), 0755, true);
            }

            if (str_ends_with($filename, '.gz')) {
                $tempSqlite = storage_path('app/backups/temp_restore.sqlite');
                $fpIn = gzopen($backupPath, 'rb');
                $fpOut = fopen($tempSqlite, 'wb');
                while (! gzeof($fpIn)) {
                    fwrite($fpOut, gzread($fpIn, 1024 * 512));
                }
                fclose($fpOut);
                gzclose($fpIn);

                File::copy($tempSqlite, $dbPath);
                File::delete($tempSqlite);
            } else {
                File::copy($backupPath, $dbPath);
            }

            $this->info("✅ SQLite database successfully restored from {$filename}!");
            Log::info("SQLite database restored from {$filename}");

            return Command::SUCCESS;
        }

        // MySQL Restore
        $dbUser = config('database.connections.mysql.username');
        $dbPass = config('database.connections.mysql.password');
        $dbHost = config('database.connections.mysql.host', '127.0.0.1');
        $dbPort = config('database.connections.mysql.port', '3306');

        $mysqlBin = 'mysql';
        if (File::exists('C:\Program Files\MySQL\MySQL Server 8.4\bin\mysql.exe')) {
            $mysqlBin = '"C:\Program Files\MySQL\MySQL Server 8.4\bin\mysql.exe"';
        }

        $sqlContent = '';
        if (str_ends_with($filename, '.gz')) {
            $fpIn = gzopen($backupPath, 'rb');
            while (! gzeof($fpIn)) {
                $sqlContent .= gzread($fpIn, 1024 * 512);
            }
            gzclose($fpIn);
        } else {
            $sqlContent = File::get($backupPath);
        }

        if (! empty($sqlContent)) {
            DB::unprepared($sqlContent);
            $this->info("✅ MySQL database successfully restored from {$filename}!");
            Log::info("MySQL database restored from {$filename}");

            return Command::SUCCESS;
        }

        $this->error('❌ MySQL restore failed: empty SQL file.');

        return Command::FAILURE;
    }
}
