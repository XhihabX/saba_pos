#!/usr/bin/env python3
"""
Saba POS / IOT POS — Subdomain cPanel Production Package Generator
==================================================================

Target Topology:
- Subdomain Web Root: /home/<user>/pos.sababilling.net/  (Public Assets + Bridge index.php)
- Backend Core Root:  /home/<user>/sabapos/             (Private Laravel App + Vendor + Storage)

Generates two production ZIP archives in cpanel_dist/:
1. pos_sababilling_net.zip (Upload & Extract in ~/pos.sababilling.net)
2. sabapos.zip            (Upload & Extract in ~/sabapos)
"""

import os
import sys
import shutil
import zipfile
import hashlib
import subprocess

def run_command(cmd, cwd=None, env=None):
    print(f"--> Running: {cmd}")
    res = subprocess.run(cmd, shell=True, cwd=cwd, env=env)
    if res.returncode != 0:
        print(f"❌ Error: Command failed with exit code {res.returncode}: {cmd}")
        sys.exit(res.returncode)

def get_file_hash(filepath):
    sha = hashlib.sha256()
    with open(filepath, 'rb') as f:
        while chunk := f.read(8192):
            sha.update(chunk)
    return sha.hexdigest()

def create_subdomain_index_php(output_path):
    content = """<?php

use Illuminate\\Foundation\\Application;
use Illuminate\\Http\\Request;

define('LARAVEL_START', microtime(true));
@ini_set('memory_limit', '512M');

$possibleBackendPaths = [
    __DIR__ . '/../sabapos',
    dirname(__DIR__) . '/sabapos',
    __DIR__ . '/../sabapos_backend',
    dirname(__DIR__) . '/sabapos_backend',
];

$backendPath = null;
foreach ($possibleBackendPaths as $path) {
    if (file_exists($path . '/bootstrap/app.php') && file_exists($path . '/vendor/autoload.php')) {
        $backendPath = realpath($path);
        break;
    }
}

if (! $backendPath) {
    http_response_code(500);
    die('<!DOCTYPE html><html><head><meta charset="utf-8"><title>Saba POS — System Notice</title>'
      . '<style>body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;background:#0f172a;color:#f8fafc;padding:3rem 1.5rem;display:flex;justify-content:center;align-items:center;min-height:80vh}'
      . '.card{background:#1e293b;border:1px solid #334155;border-radius:12px;max-width:620px;padding:2rem;box-shadow:0 10px 25px -5px rgba(0,0,0,0.5)}'
      . 'h2{color:#38bdf8;margin-top:0}code{background:#0f172a;color:#f43f5e;padding:0.2rem 0.4rem;border-radius:4px;font-size:0.9em}'
      . 'ol{line-height:1.6;color:#cbd5e1}li{margin-bottom:0.5rem}</style></head><body><div class="card">'
      . '<h2>Configuration Notice: Backend Directory Not Found</h2>'
      . '<p>The frontend bridge inside <code>pos.sababilling.net</code> could not detect the Laravel backend directory.</p>'
      . '<ol><li>Verify that the backend archive was extracted into <code>~/sabapos</code> beside <code>public_html</code>.</li>'
      . '<li>Ensure <code>~/sabapos/bootstrap/app.php</code> and <code>~/sabapos/vendor/autoload.php</code> exist on disk.</li>'
      . '<li>Ensure file permissions are set correctly: <code>chmod -R 775 ~/sabapos/storage ~/sabapos/bootstrap/cache</code></li></ol>'
      . '</div></body></html>');
}

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = $backendPath . '/storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register Composer autoloader...
require $backendPath . '/vendor/autoload.php';

// Bootstrap Laravel application...
/** @var Application $app */
$app = require_once $backendPath . '/bootstrap/app.php';

// Explicitly register this subdomain folder as the public web root
$app->usePublicPath(__DIR__);

// Handle the incoming web request
$app->handleRequest(Request::capture());
"""
    with open(output_path, 'w', encoding='utf-8') as f:
        f.write(content)

def create_subdomain_htaccess(output_path):
    content = """<IfModule mod_rewrite.c>
    <IfModule mod_negotiation.c>
        Options -Indexes
    </IfModule>

    RewriteEngine On

    # Force HTTPS on live domain
    RewriteCond %{HTTPS} off
    RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

    # Handle Authorization Header
    RewriteCond %{HTTP:Authorization} .
    RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]

    # Handle X-XSRF-Token Header
    RewriteCond %{HTTP:x-xsrf-token} .
    RewriteRule .* - [E=HTTP_X_XSRF_TOKEN:%{HTTP:X-XSRF-Token}]

    # Redirect Trailing Slashes If Not A Folder...
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_URI} (.+)/$
    RewriteRule ^ %1 [L,R=301]

    # Send Requests To Front Controller...
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^ index.php [L]
</IfModule>

<IfModule mod_headers.c>
    # Disable LiteSpeed & Browser HTML caching for instant deployment updates
    Header set Cache-Control "no-cache, no-store, must-revalidate"
    Header set Pragma "no-cache"
    Header set Expires 0
    Header set X-LSCACHE-PURGE "*"
</IfModule>
"""
    with open(output_path, 'w', encoding='utf-8') as f:
        f.write(content)

def main():
    root_dir = os.path.abspath(os.path.join(os.path.dirname(__file__), '..'))
    dist_dir = os.path.join(root_dir, 'cpanel_dist')

    print("=================================================================")
    print("   SABA POS — SUBDOMAIN CPANEL DEPLOYMENT PACKAGER               ")
    print("=================================================================")
    print(f"Repository Root: {root_dir}")
    print(f"Output Dist Dir: {dist_dir}\n")

    # Ensure system PATH includes Node & Composer
    env = os.environ.copy()
    extra_paths = [
        "/Users/macbookairm1/.config/herd-lite/bin",
        os.path.expanduser("~/.nvm/versions/node/v20.20.2/bin"),
        os.path.expanduser("~/.nvm/versions/node/v22.23.3/bin"),
        os.path.expanduser("~/.nvm/versions/node/v26.10.0/bin"),
        "/opt/homebrew/bin",
        "/usr/local/bin",
    ]
    env["PATH"] = ":".join(extra_paths) + ":" + env.get("PATH", "")

    # Step 1: Compile Vite Assets
    print("📦 Step 1: Building production frontend assets (Vite)...")
    run_command("npm run build", cwd=root_dir, env=env)

    # Step 2: Ensure Vendor is installed in production mode
    print("\n📦 Step 2: Optimizing Composer production autoloader...")
    run_command("composer install --no-dev --optimize-autoloader --prefer-dist --no-interaction", cwd=root_dir, env=env)

    # Clean destination directory
    if os.path.exists(dist_dir):
        shutil.rmtree(dist_dir, ignore_errors=True)
    os.makedirs(dist_dir, exist_ok=True)

    # Step 3: Package Frontend Subdomain ZIP (pos_sababilling_net.zip)
    print("\n📦 Step 3: Generating Subdomain Public Archive (pos_sababilling_net.zip)...")
    subdomain_temp = os.path.join(dist_dir, 'subdomain_temp')
    os.makedirs(subdomain_temp, exist_ok=True)

    # Copy public assets from public/
    public_src = os.path.join(root_dir, 'public')
    for item in os.listdir(public_src):
        src_item = os.path.join(public_src, item)
        dst_item = os.path.join(subdomain_temp, item)
        if item in ['index.php', '.htaccess']:
            continue
        if os.path.isdir(src_item):
            shutil.copytree(src_item, dst_item)
        else:
            shutil.copy2(src_item, dst_item)

    # Generate dedicated subdomain index.php & .htaccess
    create_subdomain_index_php(os.path.join(subdomain_temp, 'index.php'))
    create_subdomain_htaccess(os.path.join(subdomain_temp, '.htaccess'))

    subdomain_zip_path = os.path.join(dist_dir, 'pos_sababilling_net.zip')
    with zipfile.ZipFile(subdomain_zip_path, 'w', compression=zipfile.ZIP_DEFLATED) as zipf:
        for root, dirs, files in os.walk(subdomain_temp):
            for file in files:
                abs_path = os.path.join(root, file)
                rel_path = os.path.relpath(abs_path, subdomain_temp)
                zipf.write(abs_path, rel_path)

    shutil.rmtree(subdomain_temp, ignore_errors=True)
    print(f"  ✅ Created: {subdomain_zip_path} ({os.path.getsize(subdomain_zip_path):,} bytes)")

    # Step 4: Package Backend Core Archive (sabapos.zip)
    print("\n📦 Step 4: Generating Backend Core Archive with Vendor (sabapos.zip)...")
    backend_zip_path = os.path.join(dist_dir, 'sabapos.zip')

    exclude_dirs = {
        '.git',
        'node_modules',
        'cpanel_dist',
        'tests',
        '.gemini',
        '.tempmediaStorage',
        '.idea',
        '.vscode',
    }
    exclude_files = {
        'database.sqlite',
        '.env',
        'subdomain_public.zip',
        'sabapos_backend.zip',
        'pos_sababilling_net.zip',
        'sabapos.zip',
    }

    # Ensure storage subdirectories have .gitignore
    storage_subdirs = [
        'storage/app/public',
        'storage/app/subscription_proofs',
        'storage/framework/cache/data',
        'storage/framework/sessions',
        'storage/framework/views',
        'storage/logs',
        'bootstrap/cache',
    ]
    for subdir in storage_subdirs:
        abs_sub = os.path.join(root_dir, subdir)
        os.makedirs(abs_sub, exist_ok=True)
        gitkeep = os.path.join(abs_sub, '.gitignore')
        if not os.path.exists(gitkeep):
            with open(gitkeep, 'w') as f:
                f.write("*\n!.gitignore\n")

    with zipfile.ZipFile(backend_zip_path, 'w', compression=zipfile.ZIP_DEFLATED, allowZip64=True) as zipf:
        for root, dirs, files in os.walk(root_dir):
            dirs[:] = [d for d in dirs if d not in exclude_dirs]
            for file in files:
                if file in exclude_files or file.endswith('.pyc') or file.endswith('.tmp'):
                    continue
                abs_path = os.path.join(root, file)
                rel_path = os.path.relpath(abs_path, root_dir)

                # Skip bootstrap cache manifests (routes-v7.php, config.php, etc.)
                if rel_path.startswith('bootstrap' + os.sep + 'cache' + os.sep):
                    if not file.endswith('.gitignore') and not file.endswith('.gitkeep'):
                        continue

                # Skip storage sessions, views, logs, cache dumps
                if rel_path.startswith('storage' + os.sep + 'framework' + os.sep) or rel_path.startswith('storage' + os.sep + 'logs' + os.sep):
                    if not file.endswith('.gitignore') and not file.endswith('.gitkeep'):
                        continue

                # For public folder, only keep public/build and static fallbacks in backend archive
                if rel_path.startswith('public' + os.sep) and not rel_path.startswith('public' + os.sep + 'build' + os.sep):
                    if not file.endswith('.htaccess') and not file.endswith('index.php') and not file.endswith('robots.txt') and not file.endswith('favicon.ico'):
                        continue

                zipf.write(abs_path, rel_path)

    print(f"  ✅ Created: {backend_zip_path} ({os.path.getsize(backend_zip_path):,} bytes)")

    # Step 5: Verify Archive Integrities
    print("\n📦 Step 5: Verifying ZIP Archive Integrities...")
    with zipfile.ZipFile(subdomain_zip_path, 'r') as z1:
        if err := z1.testzip():
            raise RuntimeError(f"Corrupted entry in {subdomain_zip_path}: {err}")
    with zipfile.ZipFile(backend_zip_path, 'r') as z2:
        if err := z2.testzip():
            raise RuntimeError(f"Corrupted entry in {backend_zip_path}: {err}")
    print("  ✅ All ZIP archives passed integrity checks (0 corruptions)")

    # Step 6: Summary Report
    print("\n" + "=" * 65)
    print("   PRODUCTION DEPLOYMENT PACKAGES READY IN cpanel_dist/          ")
    print("=" * 65)
    print(f"1. pos_sababilling_net.zip -> Size: {os.path.getsize(subdomain_zip_path):,} bytes")
    print(f"   SHA-256: {get_file_hash(subdomain_zip_path)}")
    print(f"   Destination: Upload & Extract inside /home/<username>/pos.sababilling.net/")
    print()
    print(f"2. sabapos.zip            -> Size: {os.path.getsize(backend_zip_path):,} bytes")
    print(f"   SHA-256: {get_file_hash(backend_zip_path)}")
    print(f"   Destination: Upload & Extract inside /home/<username>/sabapos/")
    print("=" * 65)

if __name__ == '__main__':
    main()
