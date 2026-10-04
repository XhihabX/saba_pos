import os
import zipfile
import shutil
import subprocess

def create_subdomain_index_php(output_path):
    content = """<?php

use Illuminate\\Foundation\\Application;
use Illuminate\\Http\\Request;

define('LARAVEL_START', microtime(true));

$possiblePaths = [
    dirname(__DIR__) . '/sabapos',
    dirname(__DIR__) . '/sabapos_backend',
    __DIR__ . '/../sabapos',
    __DIR__ . '/../sabapos_backend',
];

$backendPath = null;
foreach ($possiblePaths as $path) {
    if (file_exists($path . '/vendor/autoload.php')) {
        $backendPath = realpath($path);
        break;
    }
}

if (!$backendPath) {
    die("Error: Could not locate sabapos backend directory.");
}

// Purge stale cache manifests
@unlink($backendPath . '/bootstrap/cache/routes-v7.php');
@unlink($backendPath . '/bootstrap/cache/config.php');

if (file_exists($maintenance = $backendPath . '/storage/framework/maintenance.php')) {
    require $maintenance;
}

require $backendPath . '/vendor/autoload.php';

/** @var Application $app */
$app = require_once $backendPath . '/bootstrap/app.php';
$app->usePublicPath(__DIR__);

$app->handleRequest(Request::capture());
"""
    with open(output_path, 'w', encoding='utf-8') as f:
        f.write(content)

def main():
    print("==========================================================")
    print("Saba POS Subdomain cPanel Deployment Package Generator")
    print("==========================================================\n")

    root_dir = os.path.dirname(os.path.abspath(__file__))
    dist_dir = os.path.join(root_dir, 'cpanel_dist')

    try:
        if os.path.exists(dist_dir):
            shutil.rmtree(dist_dir, ignore_errors=True)
    except Exception:
        pass
        
    os.makedirs(dist_dir, exist_ok=True)

    # 1. Compile Vite Production Assets
    print("--> Step 1: Building production frontend bundle...")
    env = os.environ.copy()
    node_paths = [
        os.path.expanduser("~/.nvm/versions/node/v20.20.2/bin"),
        os.path.expanduser("~/.nvm/versions/node/v22.23.3/bin"),
        os.path.expanduser("~/.nvm/versions/node/v26.10.0/bin"),
        "/opt/homebrew/bin",
        "/usr/local/bin"
    ]
    env["PATH"] = ":".join(node_paths) + ":" + env.get("PATH", "")
    subprocess.run("npm run build", shell=True, check=True, env=env)

    # 2. Package Subdomain Public Assets (subdomain_public.zip)
    print("--> Step 2: Preparing Subdomain Public Document Root ZIP...")
    public_temp = os.path.join(dist_dir, 'subdomain_public_temp')
    if os.path.exists(public_temp):
        shutil.rmtree(public_temp, ignore_errors=True)
        
    shutil.copytree(os.path.join(root_dir, 'public'), public_temp)

    # Replace index.php with cPanel subdomain path index.php
    create_subdomain_index_php(os.path.join(public_temp, 'index.php'))

    # Zip subdomain_public
    subdomain_zip_path = os.path.join(dist_dir, 'subdomain_public.zip')
    with zipfile.ZipFile(subdomain_zip_path, 'w', zipfile.ZIP_DEFLATED) as zipf:
        for root, dirs, files in os.walk(public_temp):
            for file in files:
                abs_path = os.path.join(root, file)
                rel_path = os.path.relpath(abs_path, public_temp)
                zipf.write(abs_path, rel_path)

    shutil.rmtree(public_temp, ignore_errors=True)
    print(f"[OK] Created: {subdomain_zip_path}")

    # 3. Package Backend Core (sabapos_backend.zip)
    print("--> Step 3: Packaging Backend Core Directory...")
    backend_zip_path = os.path.join(dist_dir, 'sabapos_backend.zip')
    
    exclude_dirs = {'.git', 'node_modules', 'cpanel_dist', 'tests', '.tempmediaStorage', '.gemini', '.idea', '.vscode'}
    exclude_files = {'subdomain_public.zip', 'sabapos_backend.zip', 'database.sqlite'}

    with zipfile.ZipFile(backend_zip_path, 'w', compression=zipfile.ZIP_DEFLATED, allowZip64=True) as zipf:
        for root, dirs, files in os.walk(root_dir):
            dirs[:] = [d for d in dirs if d not in exclude_dirs]
            for file in files:
                if file in exclude_files or file.endswith('.pyc') or file.endswith('.tmp'):
                    continue
                abs_path = os.path.join(root, file)
                rel_path = os.path.relpath(abs_path, root_dir)
                
                # Skip bootstrap cache files
                if rel_path.startswith('bootstrap' + os.sep + 'cache' + os.sep):
                    if not file.endswith('.gitignore') and not file.endswith('.gitkeep'):
                        continue
                # Skip storage framework cache/sessions/views/logs
                if rel_path.startswith('storage' + os.sep + 'framework' + os.sep) or rel_path.startswith('storage' + os.sep + 'logs' + os.sep):
                    if not file.endswith('.gitignore') and not file.endswith('.gitkeep'):
                        continue
                # For public folder, preserve public/build assets as a fail-safe fallback
                if rel_path.startswith('public' + os.sep) and not rel_path.startswith('public' + os.sep + 'build' + os.sep):
                    if not file.endswith('.htaccess') and not file.endswith('index.php') and not file.endswith('cpanel_setup.php') and not file.endswith('web.config'):
                        continue
                        
                zipf.write(abs_path, rel_path)

    # Verify Zip Integrity
    print("--> Verifying ZIP package integrity...")
    with zipfile.ZipFile(backend_zip_path, 'r') as check_zip:
        corrupt = check_zip.testzip()
        if corrupt:
            raise Exception(f"ZIP Corruption detected in file: {corrupt}")
    # Create convenience copies named public_html.zip and sabapos.zip for xhihab.com
    public_html_zip_path = os.path.join(dist_dir, 'public_html.zip')
    sabapos_zip_path = os.path.join(dist_dir, 'sabapos.zip')
    shutil.copyfile(subdomain_zip_path, public_html_zip_path)
    shutil.copyfile(backend_zip_path, sabapos_zip_path)

    print(f"[OK] Created xhihab.com packages: {public_html_zip_path} & {sabapos_zip_path}\n")

    print("==========================================================")
    print("SUCCESS: Production deployment ZIP packages generated in 'cpanel_dist/'!")
    print("  - public_html.zip (Extract inside /home/username/public_html)")
    print("  - sabapos.zip (Extract inside /home/username/sabapos)")
    print("==========================================================")

if __name__ == "__main__":
    main()
