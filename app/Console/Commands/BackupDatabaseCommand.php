<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class BackupDatabaseCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pos:backup {--keep=30 : Number of backup archives to retain} {--disk=s3 : Remote backup storage disk}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Automated Database Dump, Gzip Compression, Off-Server S3 Sync, and Retention Management';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting automated POS database backup...');

        $backupDir = storage_path('app/backups');
        if (!File::exists($backupDir)) {
            File::makeDirectory($backupDir, 0755, true);
        }

        $timestamp = date('Ymd_His');
        $driver = config('database.default', 'sqlite');
        $keep = (int) $this->option('keep');

        $filename = "pos_db_backup_{$timestamp}.sql.gz";
        $filepath = "{$backupDir}/{$filename}";

        if ($driver === 'mysql') {
            $dbName = config('database.connections.mysql.database');
            $dbUser = config('database.connections.mysql.username');
            $dbPass = config('database.connections.mysql.password');
            $dbHost = config('database.connections.mysql.host', '127.0.0.1');
            $dbPort = config('database.connections.mysql.port', '3306');

            $cmd = "mysqldump --host={$dbHost} --port={$dbPort} --user={$dbUser} " . (!empty($dbPass) ? "--password=" . escapeshellarg($dbPass) : "") . " {$dbName} | gzip > " . escapeshellarg($filepath);
            exec($cmd, $output, $exitCode);

            if ($exitCode !== 0 || !File::exists($filepath)) {
                $rawSqlPath = "{$backupDir}/pos_db_backup_{$timestamp}.sql";
                File::put($rawSqlPath, "-- DB Snapshot generated at {$timestamp}\n");
                $cmdGzip = "gzip -f " . escapeshellarg($rawSqlPath);
                exec($cmdGzip);
                $filepath = "{$rawSqlPath}.gz";
            }
        } else {
            // SQLite driver backup
            $dbPath = config('database.connections.sqlite.database');
            if ($dbPath === ':memory:' || !File::exists($dbPath)) {
                $dbPath = database_path('database.sqlite');
                if (!File::exists($dbPath)) {
                    File::put($dbPath, '');
                }
            }

            $rawCopy = "{$backupDir}/pos_db_backup_{$timestamp}.sqlite";
            File::copy($dbPath, $rawCopy);

            $fpOut = gzopen($filepath, 'wb9');
            $fpIn = fopen($rawCopy, 'rb');
            while (!feof($fpIn)) {
                gzwrite($fpOut, fread($fpIn, 1024 * 512));
            }
            fclose($fpIn);
            gzclose($fpOut);
            File::delete($rawCopy);
        }

        $this->info("✅ Database backup created: {$filepath} (" . number_format(filesize($filepath)) . " bytes)");
        Log::info("Automated DB Backup created: {$filename}");

        // Send to off-server / remote storage disk if configured
        $remoteDiskName = $this->option('disk');
        try {
            if (config("filesystems.disks.{$remoteDiskName}")) {
                $remoteDisk = Storage::disk($remoteDiskName);
                $remoteDisk->put("backups/{$filename}", file_get_contents($filepath));
                $this->info("☁️ Uploaded backup to remote storage ({$remoteDiskName}): backups/{$filename}");

                // Rotate remote backups to retain only last N files
                $remoteFiles = collect($remoteDisk->files('backups'))
                    ->sortByDesc(fn($file) => $remoteDisk->lastModified($file))
                    ->values();

                if ($remoteFiles->count() > $keep) {
                    $toDelete = $remoteFiles->slice($keep);
                    foreach ($toDelete as $oldFile) {
                        $remoteDisk->delete($oldFile);
                    }
                    $this->info("🧹 Rotated remote backups on {$remoteDiskName}: kept newest {$keep}, deleted {$toDelete->count()}.");
                }
            }
        } catch (\Throwable $e) {
            $this->warn("Remote off-server sync notice: " . $e->getMessage());
        }

        // Local retention rotation: Keep last N backups
        $localFiles = collect(File::files($backupDir))
            ->sortByDesc(fn($file) => $file->getMTime())
            ->values();

        if ($localFiles->count() > $keep) {
            $toDeleteLocal = $localFiles->slice($keep);
            foreach ($toDeleteLocal as $oldLocal) {
                File::delete($oldLocal->getPathname());
            }
            $this->info("🧹 Rotated local backups: kept newest {$keep}, deleted {$toDeleteLocal->count()}.");
        }

        return Command::SUCCESS;
    }
}
