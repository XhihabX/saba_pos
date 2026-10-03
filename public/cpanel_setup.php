<?php
/**
 * Saba POS - Web-based cPanel Setup & Deployment Helper
 * Designed for hosting environments without SSH terminal access.
 */

define('LARAVEL_START', microtime(true));

// Auto-detect sabapos_backend path across all cPanel directory structures
$possiblePaths = [
    dirname(__DIR__, 2) . '/sabapos_backend', // e.g. /home/user/public_html/pos -> /home/user/sabapos_backend
    dirname(__DIR__, 1) . '/sabapos_backend', // e.g. /home/user/pos -> /home/user/sabapos_backend
    __DIR__ . '/../sabapos_backend',
    __DIR__ . '/../../sabapos_backend',
    __DIR__ . '/..',
];

$backendPath = null;
foreach ($possiblePaths as $path) {
    if (file_exists($path . '/vendor/autoload.php')) {
        $backendPath = $path;
        break;
    }
}

if (!$backendPath) {
    die("
    <body style='font-family: sans-serif; background: #0f172a; color: #f8fafc; padding: 40px; text-align: center;'>
      <div style='max-width: 600px; margin: 0 auto; background: #1e293b; padding: 30px; border-radius: 20px; border: 1px solid #334155;'>
        <h2 style='color: #f43f5e;'>❌ Error: sabapos_backend Not Found</h2>
        <p style='color: #94a3b8; font-size: 14px;'>Could not locate <code>sabapos_backend</code> directory containing Composer autoload files.</p>
        <p style='color: #cbd5e1; font-size: 13px;'>Ensure <code>sabapos_backend.zip</code> is extracted to your cPanel home directory (e.g. <code>/home/username/sabapos_backend</code>).</p>
      </div>
    </body>");
}

require $backendPath . '/vendor/autoload.php';
$app = require_once $backendPath . '/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$action = $_GET['action'] ?? null;
$outputLog = [];

if ($action) {
    try {
        if ($action === 'fresh_seed') {
            try {
                Illuminate\Support\Facades\DB::statement('SET FOREIGN_KEY_CHECKS = 0;');
                $tables = Illuminate\Support\Facades\DB::select('SHOW TABLES');
                foreach ($tables as $table) {
                    $tableArray = (array)$table;
                    $tableName = current($tableArray);
                    Illuminate\Support\Facades\Schema::dropIfExists($tableName);
                }
                Illuminate\Support\Facades\DB::statement('SET FOREIGN_KEY_CHECKS = 1;');
                $outputLog[] = "--> [SUCCESS] Pre-cleared all existing database tables cleanly.";
            } catch (\Throwable $e) {
                $outputLog[] = "--> [INFO] Table cleanup fallback: " . $e->getMessage();
            }

            Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
            $outputLog[] = "--> [SUCCESS] Fresh Migration: " . trim(Illuminate\Support\Facades\Artisan::output());
            
            Illuminate\Support\Facades\Artisan::call('db:seed', ['--force' => true]);
            $outputLog[] = "--> [SUCCESS] Database Seeding: " . trim(Illuminate\Support\Facades\Artisan::output());
        } elseif ($action === 'migrate_seed') {
            Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
            $outputLog[] = "--> [SUCCESS] Database Migration: " . trim(Illuminate\Support\Facades\Artisan::output());
            
            Illuminate\Support\Facades\Artisan::call('db:seed', ['--force' => true]);
            $outputLog[] = "--> [SUCCESS] Database Seeding: " . trim(Illuminate\Support\Facades\Artisan::output());
        } elseif ($action === 'clear_cache') {
            Illuminate\Support\Facades\Artisan::call('config:clear');
            $outputLog[] = "--> [SUCCESS] Config Cache Cleared";
            
            Illuminate\Support\Facades\Artisan::call('route:clear');
            $outputLog[] = "--> [SUCCESS] Route Cache Cleared";
            
            Illuminate\Support\Facades\Artisan::call('cache:clear');
            $outputLog[] = "--> [SUCCESS] Application Cache Cleared";
            
            Illuminate\Support\Facades\Artisan::call('view:clear');
            $outputLog[] = "--> [SUCCESS] View Cache Cleared";
        } elseif ($action === 'optimize') {
            Illuminate\Support\Facades\Artisan::call('config:cache');
            $outputLog[] = "--> [SUCCESS] Config Cached";
            
            Illuminate\Support\Facades\Artisan::call('route:cache');
            $outputLog[] = "--> [SUCCESS] Route Cached";
        } elseif ($action === 'storage_link') {
            Illuminate\Support\Facades\Artisan::call('storage:link');
            $outputLog[] = "--> [SUCCESS] Storage Link Created: " . trim(Illuminate\Support\Facades\Artisan::output());
        }
    } catch (\Throwable $e) {
        $outputLog[] = "--> [ERROR] Execution Exception: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Saba POS - Web cPanel Deployment Runner</title>
  <style>
    body { font-family: system-ui, -apple-system, sans-serif; background: #0f172a; color: #f8fafc; margin: 0; padding: 24px; }
    .card { max-width: 760px; margin: 0 auto; background: #1e293b; border: 1px solid #334155; border-radius: 24px; padding: 32px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5); }
    h1 { font-size: 24px; font-weight: 900; margin: 0 0 8px 0; color: #ffffff; }
    p { color: #94a3b8; font-size: 13px; line-height: 1.5; margin: 0 0 24px 0; }
    .badge { display: inline-block; padding: 4px 12px; background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.3); color: #34d399; font-size: 11px; font-weight: 800; border-radius: 9999px; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 12px; }
    .grid { display: grid; grid-template-cols: 1fr 1fr; gap: 12px; margin-bottom: 24px; }
    .btn { display: flex; items-center; justify-content: center; padding: 14px 20px; border-radius: 14px; text-decoration: none; font-weight: 800; font-size: 13px; border: none; cursor: pointer; transition: all 0.2s; text-align: center; }
    .btn-danger { background: linear-gradient(135deg, #e11d48, #be123c); color: #ffffff; box-shadow: 0 10px 15px -3px rgba(225, 29, 72, 0.3); grid-column: span 2; }
    .btn-danger:hover { transform: translateY(-2px); opacity: 0.95; }
    .btn-primary { background: linear-gradient(135deg, #10b981, #059669); color: #ffffff; box-shadow: 0 10px 15px -3px rgba(16, 185, 129, 0.3); }
    .btn-primary:hover { transform: translateY(-2px); opacity: 0.95; }
    .btn-secondary { background: #334155; color: #f8fafc; border: 1px solid #475569; }
    .btn-secondary:hover { background: #475569; }
    .terminal { background: #090d16; border: 1px solid #1e293b; border-radius: 16px; padding: 16px; font-family: ui-monospace, SFMono-Regular, monospace; font-size: 12px; color: #38bdf8; min-height: 120px; overflow-x: auto; white-space: pre-wrap; }
    .success { color: #34d399; }
    .error { color: #f43f5e; }
  </style>
</head>
<body>
  <div class="card">
    <span class="badge">⚡ cPanel Web Setup Runner</span>
    <h1>Saba POS System Installer</h1>
    <p>Run database migrations, seeds, and cache clearings directly from your browser without SSH terminal access.</p>

    <div class="grid">
      <a href="?action=fresh_seed" onclick="return confirm('🔥 Warning: This will wipe all existing tables in database \'sababill_pos\' and recreate them from scratch. Proceed?')" class="btn btn-danger">🔥 Fresh Migration & Seed (Wipe & Re-create DB)</a>
      <a href="?action=migrate_seed" class="btn btn-primary">⚡ Standard Migrate & Seed</a>
      <a href="?action=clear_cache" class="btn btn-secondary">🧹 Clear System Caches</a>
      <a href="?action=optimize" class="btn btn-secondary">🚀 Cache & Optimize Routes</a>
      <a href="?action=storage_link" class="btn btn-secondary">🔗 Create Storage Link</a>
    </div>

    <div class="terminal">
<?php
if (!empty($outputLog)) {
    foreach ($outputLog as $line) {
        if (str_contains($line, '[SUCCESS]')) {
            echo "<span class='success'>" . htmlspecialchars($line) . "</span>\n";
        } else {
            echo "<span class='error'>" . htmlspecialchars($line) . "</span>\n";
        }
    }
} else {
    echo "Ready for commands. Click any button above to execute.";
}
?>
    </div>

    <div style="margin-top: 24px; padding-top: 16px; border-top: 1px solid #334155; font-size: 11px; color: #64748b; text-align: center;">
      After setting up database and caches, launch your portal at <a href="/" style="color: #38bdf8; font-weight: bold; text-decoration: none;">https://pos.sababilling.net</a>
    </div>
  </div>
</body>
</html>
