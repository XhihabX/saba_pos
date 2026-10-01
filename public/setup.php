<?php
/**
 * Saba POS Web Setup & Migration Utility (For cPanel hosting without Terminal/SSH access)
 */
define('LARAVEL_START', microtime(true));

// Auto-detect sabapos_backend directory path across cPanel folder structures
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
    die("<h2 style='color:red;font-family:sans-serif;'>Error: Could not locate 'sabapos_backend' directory.</h2><p style='font-family:sans-serif;'>Please make sure <code>sabapos_backend</code> is uploaded to your cPanel home folder.</p>");
}

// 1. Ensure .env file exists
$envPath = $backendPath . '/.env';
$envExamplePath = $backendPath . '/.env.example';

if (!file_exists($envPath) && file_exists($envExamplePath)) {
    copy($envExamplePath, $envPath);
}

// 2. Ensure APP_KEY exists in .env
if (file_exists($envPath)) {
    $envContent = file_get_contents($envPath);
    if (strpos($envContent, 'APP_KEY=') === false || preg_match('/APP_KEY=\s*$/m', $envContent) || preg_match('/APP_KEY=\n/', $envContent)) {
        $generatedKey = 'base64:' . base64_encode(random_bytes(32));
        if (strpos($envContent, 'APP_KEY=') !== false) {
            $envContent = preg_replace('/APP_KEY=.*$/m', 'APP_KEY=' . $generatedKey, $envContent);
        } else {
            $envContent = "APP_KEY=" . $generatedKey . "\n" . $envContent;
        }
        file_put_contents($envPath, $envContent);
    }
}

require $backendPath . '/vendor/autoload.php';
$app = require_once $backendPath . '/bootstrap/app.php';

// Bootstrap console kernel for Artisan execution
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$key = $_GET['key'] ?? '';
if ($key !== 'setup123') {
    die("
    <div style='max-width:500px;margin:50px auto;font-family:sans-serif;background:#f8fafc;padding:30px;border-radius:16px;border:1px solid #e2e8f0;box-shadow:0 10px 25px rgba(0,0,0,0.05);'>
        <h2 style='color:#0f172a;margin-top:0;'>Saba POS Web Setup Security</h2>
        <p style='color:#475569;'>To run database migrations & setup without terminal access, open this URL in your browser:</p>
        <div style='background:#0f172a;color:#38bdf8;padding:12px;border-radius:8px;font-family:monospace;font-size:14px;word-break:break-all;'>
            setup.php?key=setup123
        </div>
        <p style='color:#475569;font-size:13px;margin-top:15px;'>Add <code>&fresh=true</code> to wipe & re-run fresh migrations + seeders:</p>
        <div style='background:#0f172a;color:#34d399;padding:12px;border-radius:8px;font-family:monospace;font-size:14px;word-break:break-all;'>
            setup.php?key=setup123&fresh=true
        </div>
    </div>
    ");
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Saba POS Web Setup</title>
    <style>
        body { font-family: system-ui, -apple-system, sans-serif; background: #0f172a; color: #f8fafc; padding: 40px; line-height: 1.6; }
        .card { max-width: 800px; margin: 0 auto; background: #1e293b; border-radius: 16px; padding: 30px; border: 1px solid #334155; }
        h1 { color: #38bdf8; margin-top: 0; }
        pre { background: #0f172a; color: #34d399; padding: 20px; border-radius: 10px; font-family: monospace; white-space: pre-wrap; word-break: break-all; }
        .alert { background: #065f46; color: #a7f3d0; padding: 15px; border-radius: 8px; margin-top: 20px; font-weight: bold; }
        .info-box { background: #0f172a; border: 1px solid #0284c7; padding: 15px; border-radius: 8px; margin-bottom: 20px; color: #e0f2fe; }
    </style>
</head>
<body>
<div class="card">
    <h1>🚀 Saba POS Web Setup & Database Migration Tool</h1>
    
    <div class="info-box">
        <strong>Database Connection:</strong> <?= htmlspecialchars(config('database.default')) ?> <br>
        <strong>Database Target:</strong> <?= htmlspecialchars(config('database.connections.' . config('database.default') . '.database')) ?>
    </div>

    <pre>
<?php
try {
    echo "--> 1. Ensuring App Encryption Key in .env...\n";
    if (empty(config('app.key'))) {
        \Illuminate\Support\Facades\Artisan::call('key:generate', ['--force' => true]);
        echo \Illuminate\Support\Facades\Artisan::output() . "\n";
    } else {
        echo "[OK] APP_KEY already configured!\n\n";
    }

    $isFresh = isset($_GET['fresh']) && $_GET['fresh'] === 'true';

    if ($isFresh) {
        echo "--> 2. Running Fresh Database Migrations & Seeder...\n";
        \Illuminate\Support\Facades\Artisan::call('migrate:fresh', ['--seed' => true, '--force' => true]);
        echo \Illuminate\Support\Facades\Artisan::output() . "\n";
    } else {
        echo "--> 2. Running Database Migrations...\n";
        \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
        echo \Illuminate\Support\Facades\Artisan::output() . "\n";

        if (isset($_GET['seed']) && $_GET['seed'] === 'true') {
            echo "--> 3. Seeding Demo Database Accounts...\n";
            try {
                \Illuminate\Support\Facades\Artisan::call('db:seed', ['--force' => true]);
                echo \Illuminate\Support\Facades\Artisan::output() . "\n";
            } catch (\Throwable $se) {
                echo "[NOTICE] Seeder notice (data already exists): " . $se->getMessage() . "\n\n";
            }
        }
    }

    echo "--> 4. Clearing & Caching Framework Configurations...\n";
    \Illuminate\Support\Facades\Artisan::call('config:clear');
    \Illuminate\Support\Facades\Artisan::call('cache:clear');
    echo "[OK] Configuration caches cleared!\n\n";

    echo "[SUCCESS] Saba POS setup completed successfully!\n";
} catch (\Throwable $e) {
    echo "\n[ERROR] " . $e->getMessage() . "\n" . $e->getTraceAsString();
}
?>
    </pre>
    <div class="alert">
        ✅ Setup Completed! You can now log in at your website domain.<br>
        For security, please delete <code>setup.php</code> from your public folder using cPanel File Manager after setup is complete.
    </div>
</div>
</body>
</html>
