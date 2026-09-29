<?php
// Saba POS Web Installer for cPanel hosting without terminal access

$possiblePaths = [
    dirname(__DIR__, 2) . '/sabapos_backend',
    dirname(__DIR__, 1) . '/sabapos_backend',
    __DIR__ . '/../sabapos_backend',
    __DIR__ . '/../../sabapos_backend',
];

$backendPath = null;
foreach ($possiblePaths as $path) {
    if (file_exists($path . '/vendor/autoload.php')) {
        $backendPath = $path;
        break;
    }
}

// Ensure .env file exists
if (!file_exists($backendPath . '/.env') && file_exists($backendPath . '/.env.example')) {
    @copy($backendPath . '/.env.example', $backendPath . '/.env');
}

// Purge any stale bootstrap cache files so updated .env settings take effect immediately
$cacheFiles = glob($backendPath . '/bootstrap/cache/*.php');
if ($cacheFiles) {
    foreach ($cacheFiles as $file) {
        @unlink($file);
    }
}

require $backendPath . '/vendor/autoload.php';
$app = require_once $backendPath . '/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "<!DOCTYPE html><html><head><title>Saba POS Web Installer</title>";
echo "<style>body{font-family:sans-serif;padding:40px;background:#f8fafc;color:#0f172a;} pre{background:#1e293b;color:#f8fafc;padding:15px;border-radius:8px;overflow-x:auto;} .card{background:white;padding:30px;border-radius:12px;box-shadow:0 4px 6px -1px rgba(0,0,0,0.1);max-width:700px;margin:0 auto;}</style></head><body>";
echo "<div class='card'>";
echo "<h1 style='color:#059669;'>🚀 Saba POS Live Setup</h1>";

try {
    // Auto-create database.sqlite if using SQLite connection
    if (config('database.default') === 'sqlite') {
        $sqlitePath = config('database.connections.sqlite.database');
        if ($sqlitePath && !file_exists($sqlitePath) && str_ends_with($sqlitePath, '.sqlite')) {
            @mkdir(dirname($sqlitePath), 0755, true);
            @touch($sqlitePath);
        }
    }

    echo "<p><b>⏳ Step 1: Running Database Migrations & Seeders...</b></p>";
    \Illuminate\Support\Facades\Artisan::call('migrate:fresh', [
        '--seed' => true,
        '--force' => true,
    ]);
    echo "<pre>" . \Illuminate\Support\Facades\Artisan::output() . "</pre>";

    echo "<p><b>⚡ Step 2: Caching Routes & Configuration...</b></p>";
    \Illuminate\Support\Facades\Artisan::call('config:cache');
    \Illuminate\Support\Facades\Artisan::call('route:cache');
    \Illuminate\Support\Facades\Artisan::call('view:cache');
    echo "<pre>Config and routes cached successfully.</pre>";

    echo "<div style='margin-top:20px;padding:15px;background:#d1fae5;border:1px solid #10b981;border-radius:8px;color:#065f46;'>";
    echo "<h3 style='margin:0 0 10px 0;'>✅ SUCCESS! Database initialized, seeded, and cached!</h3>";
    echo "<p style='margin:0 0 15px 0;'>Your Saba POS SaaS portal is now 100% ready.</p>";
    echo "<a href='/login' style='display:inline-block;padding:12px 24px;background:#059669;color:white;text-decoration:none;border-radius:8px;font-weight:bold;'>Go to Saba POS Login →</a>";
    echo "</div>";
    echo "<p style='margin-top:20px;color:#ef4444;font-size:13px;'>⚠️ <b>Security Notice:</b> Please delete <code>install.php</code> from your subdomain folder now.</p>";

} catch (\Exception $e) {
    echo "<div style='margin-top:20px;padding:15px;background:#fee2e2;border:1px solid #ef4444;border-radius:8px;color:#991b1b;'>";
    echo "<h3 style='margin:0 0 10px 0;'>❌ Setup Error:</h3>";
    echo "<pre style='background:#7f1d1d;'>" . htmlspecialchars($e->getMessage()) . "</pre>";
    echo "</div>";
}

echo "</div></body></html>";
