import os
import zipfile
import shutil
import subprocess

def create_subdomain_index_php(output_path):
    content = """<?php

use Illuminate\\Foundation\\Application;
use Illuminate\\Http\\Request;

define('LARAVEL_START', microtime(true));

// Auto-detect sabapos_backend path across all cPanel folder structures
$possiblePaths = [
    dirname(__DIR__, 2) . '/sabapos_backend', // e.g., /home/user/public_html/pos -> /home/user/sabapos_backend
    dirname(__DIR__, 1) . '/sabapos_backend', // e.g., /home/user/pos -> /home/user/sabapos_backend
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
    die("<h2 style='color:red;font-family:sans-serif;'>Error: Could not locate 'sabapos_backend' directory.</h2><p style='font-family:sans-serif;'>Please make sure <code>sabapos_backend</code> is uploaded to your cPanel home directory (e.g., <code>/home/username/sabapos_backend</code>).</p>");
}

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
                # For public folder, include build assets as a fallback
                if rel_path.startswith('public' + os.sep) and not rel_path.startswith('public' + os.sep + 'build' + os.sep):
                    if not file.endswith('.htaccess') and not file.endswith('index.php') and not file.endswith('web.config'):
                        continue
                        
                zipf.write(abs_path, rel_path)

    # Verify Zip Integrity
    print("--> Verifying ZIP package integrity...")
    with zipfile.ZipFile(backend_zip_path, 'r') as check_zip:
        corrupt = check_zip.testzip()
        if corrupt:
            raise Exception(f"ZIP Corruption detected in file: {corrupt}")
        print(f"[OK] Backend ZIP verified successfully! Total files: {len(check_zip.namelist())}")

    print(f"[OK] Created: {backend_zip_path}\n")

    print("==========================================================")
    print("SUCCESS: Subdomain deployment ZIP packages generated in 'cpanel_dist/'!")
    print("==========================================================")

if __name__ == "__main__":
    main()
