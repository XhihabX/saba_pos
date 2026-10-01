<?php
/**
 * Saba POS Diagnostic & Error Troubleshooting Tool
 */
define('LARAVEL_START', microtime(true));

error_reporting(E_ALL);
ini_set('display_errors', 1);

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

if (!$backendPath) {
    die("<h2 style='color:red;font-family:sans-serif;'>Error: Could not locate 'sabapos_backend' directory.</h2>");
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Saba POS Diagnostic Tool</title>
    <style>
        body { font-family: monospace; background: #0f172a; color: #f8fafc; padding: 30px; line-height: 1.5; }
        .card { max-width: 900px; margin: 0 auto; background: #1e293b; padding: 25px; border-radius: 12px; border: 1px solid #334155; }
        h2 { color: #38bdf8; margin-top: 0; }
        h3 { color: #f1f5f9; border-bottom: 1px solid #475569; padding-bottom: 5px; }
        .pass { color: #34d399; font-weight: bold; }
        .fail { color: #f87171; font-weight: bold; }
        ul { list-style: none; padding-left: 0; }
        li { padding: 4px 0; border-bottom: 1px solid #334155; }
        pre { background: #0f172a; padding: 15px; border-radius: 8px; overflow-x: auto; color: #e2e8f0; font-size: 13px; }
    </style>
</head>
<body>
<div class="card">
    <h2>🔍 Saba POS System Diagnostics</h2>

    <h3>1. Server Environment</h3>
    <ul>
        <li>PHP Version: <strong><?= PHP_VERSION ?></strong> (<?= PHP_VERSION_ID >= 80200 ? '<span class="pass">PASS (>= 8.2)</span>' : '<span class="fail">FAIL (< 8.2)</span>' ?>)</li>
        <li>PDO MySQL Extension: <?= extension_loaded('pdo_mysql') ? '<span class="pass">INSTALLED</span>' : '<span class="fail">MISSING</span>' ?></li>
        <li>PDO SQLite Extension: <?= extension_loaded('pdo_sqlite') ? '<span class="pass">INSTALLED</span>' : '<span class="fail">MISSING</span>' ?></li>
        <li>Mbstring Extension: <?= extension_loaded('mbstring') ? '<span class="pass">INSTALLED</span>' : '<span class="fail">MISSING</span>' ?></li>
        <li>Fileinfo Extension: <?= extension_loaded('fileinfo') ? '<span class="pass">INSTALLED</span>' : '<span class="fail">MISSING (Enable fileinfo in cPanel -> Select PHP Version)</span>' ?></li>
        <li>OpenSSL Extension: <?= extension_loaded('openssl') ? '<span class="pass">INSTALLED</span>' : '<span class="fail">MISSING</span>' ?></li>
    </ul>

    <h3>2. Folder & File Permissions</h3>
    <ul>
        <?php
        $storage = $backendPath . '/storage';
        $cache = $backendPath . '/bootstrap/cache';
        $sqlite = $backendPath . '/database/database.sqlite';
        $env = $backendPath . '/.env';
        ?>
        <li>Backend Path: <code><?= htmlspecialchars($backendPath) ?></code></li>
        <li>Storage Folder Writable: <?= is_writable($storage) ? '<span class="pass">YES</span>' : '<span class="fail">NO (chmod 775/777 required)</span>' ?></li>
        <li>Bootstrap Cache Writable: <?= is_writable($cache) ? '<span class="pass">YES</span>' : '<span class="fail">NO (chmod 775/777 required)</span>' ?></li>
        <li>SQLite File Writable: <?= file_exists($sqlite) ? (is_writable($sqlite) ? '<span class="pass">YES</span>' : '<span class="fail">NO (chmod 666/777 required)</span>') : '<span>N/A (SQLite file not created yet)</span>' ?></li>
        <li>.env File Exists: <?= file_exists($env) ? '<span class="pass">YES</span>' : '<span class="fail">NO</span>' ?></li>
    </ul>

    <h3>3. Framework Boot & Database Test</h3>
    <pre>
<?php
try {
    require $backendPath . '/vendor/autoload.php';
    $app = require_once $backendPath . '/bootstrap/app.php';
    $app->usePublicPath(__DIR__);
    $kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
    $kernel->bootstrap();

    echo "[OK] Laravel App & Kernel Booted Successfully!\n";
    echo "App Name: " . config('app.name') . "\n";
    echo "App Env: " . config('app.env') . "\n";
    echo "App Debug: " . (config('app.debug') ? 'TRUE' : 'FALSE') . "\n";
    echo "App URL: " . config('app.url') . "\n";
    echo "DB Connection: " . config('database.default') . "\n";
    echo "DB Host/Path: " . config('database.connections.' . config('database.default') . '.database') . "\n";
    echo "Public Path: " . public_path() . "\n";
    echo "Vite Manifest Exists: " . (file_exists(public_path('build/manifest.json')) ? 'YES (' . public_path('build/manifest.json') . ')' : 'NO (MISSING AT ' . public_path('build/manifest.json') . ')') . "\n";

    echo "\n--> Testing Database Query...\n";
    $userCount = \App\Models\User::count();
    echo "[OK] DB Query Success! Total Users in Database: " . $userCount . "\n";

} catch (\Throwable $e) {
    echo "[ERROR THROWN]:\n" . $e->getMessage() . "\n\nFile: " . $e->getFile() . ":" . $e->getLine() . "\n\nTrace:\n" . $e->getTraceAsString();
}
?>
    </pre>
</div>
</body>
</html>
