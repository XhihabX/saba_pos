# cPanel Production Deployment Guide — `pos.sababilling.net`

This document is the definitive production deployment manual for hosting **Saba POS** on shared cPanel hosting under the production subdomain `pos.sababilling.net`, with the Laravel backend isolated outside the public web root in `~/sabapos`.

---

## 1. Architecture & Directory Layout

To protect secrets (`.env`), private file uploads (bank deposit slips, subscription payment proofs), SQLite fallback databases, and framework internals from direct public HTTP access, the application code is placed in `~/sabapos` (in the cPanel user home directory, alongside `public_html`), while only the compiled public assets and index bootstrap are exposed to the web.

```
/home/<cpanel_user>/
├── sabapos/                          <-- Laravel application backend (PRIVATE, NOT ACCESSIBLE VIA HTTP)
│   ├── app/
│   ├── bootstrap/
│   │   └── cache/                    <-- Writable (775)
│   ├── config/
│   ├── database/
│   ├── public/                       <-- Web root source
│   │   ├── build/                    <-- Compiled Vite assets (manifest, js, css)
│   │   ├── index.php
│   │   └── .htaccess
│   ├── storage/                      <-- Writable (775)
│   │   ├── app/
│   │   │   └── subscription_proofs/  <-- Private disk (bank slips, payment proofs)
│   │   ├── framework/
│   │   └── logs/
│   ├── .env                          <-- Writable by owner only (600), OUTSIDE web root
│   └── artisan
│
├── public_html/                      <-- Primary domain root (leave untouched)
└── pos.sababilling.net/              <-- Subdomain document root (PUBLIC)
    ├── .htaccess                     <-- Apache URL rewrite & HTTPS enforcement
    ├── index.php                     <-- Points to /home/<cpanel_user>/sabapos/public/index.php
    ├── build/ -> ~/sabapos/public/build
    ├── favicon.ico
    └── robots.txt
```

---

## 2. Step 1: Subdomain Setup in cPanel

1. Log in to your **cPanel Dashboard** (`https://sababilling.net:2083`).
2. Navigate to **Domains** (or **Subdomains** depending on cPanel theme).
3. Click **Create A New Domain**:
   - **Domain**: `pos.sababilling.net`
   - **Share document root**: **UNCHECK** this checkbox.
   - **Document Root**: Enter `pos.sababilling.net` (or `public_html/pos`).
4. Click **Submit**.

---

## 3. Step 2: MultiPHP Configuration & CLI PHP Path

### 3.1 Web PHP Version
1. In cPanel, navigate to **MultiPHP Manager**.
2. Locate `pos.sababilling.net` in the domain table.
3. Select **PHP 8.3** (or **PHP 8.4** if provided by your host).
4. Click **Apply**.
5. Navigate to **MultiPHP INI Editor**:
   - Select `pos.sababilling.net`.
   - Ensure the following values are configured:
     - `memory_limit = 256M` (or `512M` for reports)
     - `upload_max_filesize = 10M`
     - `post_max_size = 12M`
     - `max_execution_time = 120`

### 3.2 Finding the Exact CLI PHP Path
On cPanel shared servers, the default `/usr/bin/php` may point to an older system PHP (e.g. PHP 7.4 or 8.0). You must use the host's explicit CloudLinux/cPanel EA4 PHP binary path.

Open cPanel **Terminal** (or connect via SSH) and run:
```bash
which ea-php83 || which ea-php84 || which php83 || which php84
```
Common cPanel CLI PHP binary paths:
- `/usr/local/bin/ea-php83` *(Most common on cPanel EA4)*
- `/usr/local/bin/ea-php84`
- `/opt/cpanel/ea-php83/root/usr/bin/php`
- `/usr/bin/php83`

Verify the CLI binary version:
```bash
/usr/local/bin/ea-php83 -v
# Output must indicate PHP 8.3.x or 8.4.x
```
*Note this exact path — you will need it for cron jobs and running Artisan commands.*

---

## 4. Step 3: MySQL Database & User Creation

1. In cPanel, navigate to **MySQL® Databases**.
2. Under **Create New Database**:
   - Database Name: `sababilling_pos` (cPanel prepends your username: `<username>_sabapos`).
   - Click **Create Database**.
3. Under **Add New User**:
   - Username: `sababilling_posusr` (`<username>_posusr`).
   - Password: Click **Password Generator** (minimum 20 characters, mixed case, numbers, symbols). Save this password securely!
   - Click **Create User**.
4. Under **Add User To Database**:
   - Select user `<username>_posusr` and database `<username>_sabapos`.
   - Click **Add**.
   - Check **ALL PRIVILEGES**.
   - Click **Make Changes**.

---

## 5. Step 4: Code Deployment & Document Root Setup

### 5.1 Upload Backend to `~/sabapos`
1. On your local machine or build runner, ensure frontend assets are compiled:
   ```bash
   npm run build
   ```
2. Archive the project (excluding `node_modules`, `.git`, `.env`, and `tests`):
   ```bash
   tar -czf sabapos_deploy.tar.gz \
     --exclude='node_modules' \
     --exclude='.git' \
     --exclude='.env' \
     --exclude='tests' \
     .
   ```
3. In cPanel **File Manager**:
   - Go to `/home/<username>/` (your home directory).
   - Create a new directory named `sabapos`.
   - Upload `sabapos_deploy.tar.gz` into `/home/<username>/sabapos/` and **Extract** it.
   - Delete `sabapos_deploy.tar.gz`.

### 5.2 Install Composer Dependencies (Production Mode)
In cPanel **Terminal** (or SSH):
```bash
cd /home/<username>/sabapos
/usr/local/bin/ea-php83 /usr/local/bin/composer install --no-dev --optimize-autoloader --prefer-dist
```

### 5.3 Configure Document Root (`~/pos.sababilling.net`)
Option A — **Recommended: Direct Document Root via cPanel**:
If your cPanel allows pointing document root directly to `sabapos/public`, edit the domain in cPanel **Domains** and set the Document Root to:
`/home/<username>/sabapos/public`

Option B — **Symlink or Bridge in `~/pos.sababilling.net`**:
If cPanel restricts document roots inside the home folder, place this `index.php` inside `/home/<username>/pos.sababilling.net/index.php`:
```php
<?php

use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../sabapos/storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../sabapos/vendor/autoload.php';

// Bootstrap Laravel and handle the request...
(require_once __DIR__.'/../sabapos/bootstrap/app.php')
    ->handleRequest(Request::capture());
```

Copy the compiled build assets and public files:
```bash
cd /home/<username>/pos.sababilling.net
cp -r /home/<username>/sabapos/public/build .
cp /home/<username>/sabapos/public/favicon.ico .
cp /home/<username>/sabapos/public/robots.txt .
cp /home/<username>/sabapos/public/.htaccess .
```

Verify `.htaccess` enforces HTTPS and clean URLs:
```apache
<IfModule mod_rewrite.c>
    <IfModule mod_negotiation.c>
        Options -MultiViews -Indexes
    </IfModule>

    RewriteEngine On

    # Force HTTPS
    RewriteCond %{HTTPS} off
    RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

    # Handle Authorization Header
    RewriteCond %{HTTP:Authorization} .
    RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]

    # Redirect Trailing Slashes...
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_URI} (.+)/$
    RewriteRule ^ %1 [L,R=301]

    # Send Requests To Front Controller...
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^ index.php [L]
</IfModule>
```

---

## 6. Step 5: Secure `.env` Configuration (`chmod 600`)

Create `/home/<username>/sabapos/.env` (located completely outside the web root):

```ini
APP_NAME="Saba POS"
APP_ENV=production
APP_KEY=base64:YOUR_APP_KEY_GENERATED_BY_ARTISAN
APP_DEBUG=false
APP_URL=https://pos.sababilling.net

# Database Credentials
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=<username>_sabapos
DB_USERNAME=<username>_posusr
DB_PASSWORD="YOUR_STRONG_PASSWORD_HERE"

# Queue & Cache Drivers (Must be database on cPanel shared hosting)
QUEUE_CONNECTION=database
CACHE_STORE=database
SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_SECURE_COOKIE=true

# Daily Log Retention
LOG_CHANNEL=stack
LOG_STACK=daily
LOG_LEVEL=info
LOG_DAILY_DAYS=14

# Production Security
FORCE_HTTPS=true

# Reverse Proxy & Trusted Proxies (Anti-Spoofing)
# Leave EMPTY on standard cPanel where visitors connect directly to Apache.
# This ensures untrusted X-Forwarded-For headers are completely ignored by Laravel.
# If your domain sits behind Cloudflare proxy, paste Cloudflare IP ranges:
# TRUSTED_PROXIES=173.245.48.0/20,103.21.244.0/22,103.22.200.0/22,103.31.4.0/22,141.101.64.0/18,108.162.192.0/18,190.93.240.0/20,188.114.96.0/20,197.234.240.0/22,198.41.128.0/17,162.158.0.0/15,104.16.0.0/13,104.24.0.0/14,172.64.0.0/13,131.0.72.0/22
TRUSTED_PROXIES=

# Bangladesh Regional Compliance Secrets
EFD_SECRET_KEY=
MFS_WEBHOOK_SECRET=

# Mail Setup (Configure your cPanel SMTP)
MAIL_MAILER=smtp
MAIL_HOST=mail.sababilling.net
MAIL_PORT=465
MAIL_USERNAME=noreply@sababilling.net
MAIL_PASSWORD="SMTP_PASSWORD"
MAIL_ENCRYPTION=ssl
MAIL_FROM_ADDRESS="noreply@sababilling.net"
MAIL_FROM_NAME="Saba POS"

# Off-Server Cloud Backup Storage (Recommended S3 / Wasabi)
AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=ap-southeast-1
AWS_BUCKET=sabapos-offsite-backups
```

Set strict file permissions so only your cPanel Linux user can read the `.env` file:
```bash
chmod 600 /home/<username>/sabapos/.env
```

---

## 7. Step 6: File & Directory Permissions

Ensure Laravel storage and bootstrap cache directories are writable by the web process:
```bash
cd /home/<username>/sabapos
chmod -R 775 storage bootstrap/cache
```
*Note: On cPanel suPHP/FastCGI/LiteSpeed setups, directories must be `755` or `775`, and files `644`. Never use `777` on cPanel, as Apache suEXEC will block the site with a 500 error.*

---

## 8. Step 7: Production Installation & First Run

Run the production installer command in cPanel Terminal:
```bash
cd /home/<username>/sabapos

# 1. Generate Application Key (if not already set)
/usr/local/bin/ea-php83 artisan key:generate --force

# 2. Run Production Installer (Migrates database, seeds SaaS Plans, prompts for Super Admin)
/usr/local/bin/ea-php83 artisan pos:install

# 3. Create private proof storage directory
/usr/local/bin/ea-php83 artisan storage:link

# 4. Cache Configurations, Routes, and Blade Views for High Performance
/usr/local/bin/ea-php83 artisan config:cache
/usr/local/bin/ea-php83 artisan route:cache
/usr/local/bin/ea-php83 artisan view:cache
```

> [!CAUTION]
> **NEVER RUN `php artisan db:seed` or `php artisan migrate:fresh --seed` IN PRODUCTION!**
> `SabaPosSeeder` contains demo retailer accounts and has an automated protection guard that intentionally aborts with an error in production environments. Only run `php artisan pos:install`.

---

## 9. Step 8: Cron Jobs Configuration (Scheduler & Queue)

In cPanel, open **Cron Jobs**.

### 9.1 Laravel Task Scheduler (Runs Every Minute)
Add the following cron line:
- **Minute**: `*`
- **Hour**: `*`
- **Day**: `*`
- **Month**: `*`
- **Weekday**: `*`
- **Command**:
  ```bash
  /usr/local/bin/ea-php83 /home/<username>/sabapos/artisan schedule:run >> /dev/null 2>&1
  ```
*This handles daily sales summary verification at 02:00, automated database backups at 03:00, unmatched MFS checks, and subscription expiration reminders.*

### 9.2 Queue Worker (Processes CSV Exports & Emails on Shared Hosting)
Because shared hosting accounts typically do not provide long-running Supervisor daemon processes, configure a self-terminating queue worker cron that runs every minute, processes pending jobs, and exits safely:
- **Minute**: `*`
- **Hour**: `*`
- **Day**: `*`
- **Month**: `*`
- **Weekday**: `*`
- **Command**:
  ```bash
  /usr/local/bin/ea-php83 /home/<username>/sabapos/artisan queue:work database --stop-when-empty --max-time=50 --tries=3 >> /dev/null 2>&1
  ```

---

## 10. Step 9: SSL/TLS & AutoSSL Verification

1. In cPanel, navigate to **SSL/TLS Status**.
2. Locate `pos.sababilling.net`.
3. Click **Run AutoSSL**.
4. Once completed, verify that a green padlock icon appears beside `pos.sababilling.net`.
5. Visit `https://pos.sababilling.net/health` in your browser to verify SSL handshake and application telemetry.

---

## 11. Step 10: Database Backup (`pos:backup`) & `mysqldump` Execution Test

The system includes automated database backup via `php artisan pos:backup`.

In cPanel Terminal, test that the PHP CLI environment can execute database backups:
```bash
cd /home/<username>/sabapos
/usr/local/bin/ea-php83 artisan pos:backup
```

### If `mysqldump` or `exec()` is restricted by your shared hosting provider:
Some strict shared hosts disable `exec()`, `shell_exec()`, and `proc_open()` in `php.ini`.
If you receive an `exec disabled` notice:
1. Check in cPanel **MultiPHP INI Editor** if `disable_functions` can be adjusted.
2. If disabled permanently by the server root policy, configure an automated cPanel cron using native MySQL command:
   ```bash
   0 3 * * * mysqldump -u <username>_posusr -p'YOUR_PASSWORD' <username>_sabapos | gzip > /home/<username>/sabapos/storage/app/backups/manual_db_backup_$(date +\%F).sql.gz
   ```
3. Use the cPanel **Backup Wizard** weekly to download complete home directory and database snapshots.

---

## 12. Step 11: Emergency Rollback Procedure

If a deployed update causes unexpected issues:

1. **Activate Maintenance Mode**:
   ```bash
   /usr/local/bin/ea-php83 /home/<username>/sabapos/artisan down --secret="emergency-admin-access"
   ```
2. **Revert Application Files**:
   Extract your previous release archive into `~/sabapos`.
3. **Roll Back Migrations (if applicable)**:
   ```bash
   /usr/local/bin/ea-php83 /home/<username>/sabapos/artisan migrate:rollback --step=1
   ```
4. **Re-cache Configurations**:
   ```bash
   /usr/local/bin/ea-php83 /home/<username>/sabapos/artisan optimize:clear
   /usr/local/bin/ea-php83 /home/<username>/sabapos/artisan config:cache
   /usr/local/bin/ea-php83 /home/<username>/sabapos/artisan route:cache
   /usr/local/bin/ea-php83 /home/<username>/sabapos/artisan view:cache
   ```
5. **Bring Site Back Online**:
   ```bash
   /usr/local/bin/ea-php83 /home/<username>/sabapos/artisan up
   ```

---

---

## 13. Step 12: Automated Post-Deployment Smoke Test Script

After completing deployment and DNS setup, run the included smoke-test script directly from your local machine or server terminal to verify all security and infrastructure gates:

```bash
cd /home/<username>/sabapos
./scripts/smoke_prod.sh https://pos.sababilling.net
```

### What `scripts/smoke_prod.sh` verifies:
1. **Host Header Forgery Protection (`400`)**: Sends an untrusted `Host: evil-attacker-forged.com` header and verifies that Symfony rejects it with `HTTP 400 Bad Request`. `[verified in tests]`
2. **HTTPS Availability (`200`)**: Queries `https://pos.sababilling.net/login` and asserts `HTTP 200 OK`. `[needs the real server]`
3. **HTTP to HTTPS Redirection (`301`/`302`)**: Insecure HTTP requests automatically redirect to secure HTTPS. `[needs the real server]`
4. **Health Check & Queue Status**: Fetches `/health` and ensures the asynchronous queue driver reports `database` and `healthy`. `[verified in tests]`
5. **Private `.env` File Inaccessibility (`404`/`403`)**: Direct HTTP GET on `/.env` is blocked by Apache rewrite rules. `[needs the real server]`
6. **Private Directories Inaccessibility (`404`/`403`)**: Direct HTTP GET on `/vendor/autoload.php` and `/storage/app/.env` are completely unreachable. `[needs the real server]`

---

## 14. Post-Deployment Verification Checklist

Every item in this checklist has been categorized honestly according to its empirical testing status:

| Verification Item | Scope | Status | Notes |
| :--- | :--- | :--- | :--- |
| **`php artisan pos:install` command** | CLI / Migrations | `[VERIFIED]` | Verified in feature test suite. Seeds SaaS plans and prompts for Super Admin. |
| **`SabaPosSeeder` production abort** | Seeder Guard | `[VERIFIED]` | Verified in feature test suite. Aborts with `RuntimeException` when `APP_ENV=production`. |
| **Host Header Forgery Rejection (400)** | Security / Kernel | `[VERIFIED]` | Verified in feature test suite. Untrusted Host headers rejected with HTTP 400 Bad Request. |
| **Trusted Proxies (`TRUSTED_PROXIES`)** | Security / Proxy | `[VERIFIED]` | Verified in feature test suite. Forged `X-Forwarded-For` ignored when TRUSTED_PROXIES is empty. |
| **Supervisor PIN Multi-Tier Lockout** | Anti-Brute-Force | `[VERIFIED]` | Verified in feature test suite. 5 failures lock user for 15m & email manager; 20 lock store for 10m. |
| **Public Registration Rate Limiting (Global & IP)** | Security / Throttle | `[VERIFIED]` | Verified in feature test suite. Capped at 30/hr globally across all IPs and 5/hr per IP. |
| **Registration Honeypot Field (`website_hp`)** | Bot Protection | `[VERIFIED]` | Verified in feature test suite. Rejects bot submissions with HTTP 422. |
| **Account Login Rate Limiting (Email & IP)** | Security / Auth | `[VERIFIED]` | Verified in feature test suite. Locks by account email (5 attempts) and IP (10 attempts). |
| **Merchant Bank Data Storage (Sanitized)** | Security / Database | `[VERIFIED]` | Verified in feature test suite. Only `account_last4` (4 digits) stored. No full bank numbers! |
| **Private Slip Storage (`local` disk)** | Storage / Privacy | `[VERIFIED]` | Verified in feature test suite. Slips stored on private disk, accessible only by Super Admin. |
| **Pessimistic Concurrency Locking on Approvals** | Financial / Billing | `[VERIFIED]` | Verified in feature test suite. `lockForUpdate` prevents double extension under concurrent requests. |
| **Unique Constraint on Payment Reference** | Database Schema | `[VERIFIED]` | Verified in database schema migration. Unique index enforces no duplicate references in MySQL. |
| **Renewal Amount Matching Plan Price** | Financial Safety | `[VERIFIED]` | Verified in feature test suite. Rejects mismatched renewal payments with HTTP 422. |
| **Platform Settings Validation & Audit Trail** | Super Admin | `[VERIFIED]` | Verified in feature test suite. Validates phone regexes and logs old vs new value diffs. |
| **Maintenance Mode Bypass for Super Admin** | Operations | `[VERIFIED]` | Verified in feature test suite. Super Admin can access platform during maintenance mode. |
| **Empty Payment Defaults on Registration** | Frontend / UX | `[VERIFIED]` | Verified in Vue views. Displays "payment details not configured" notice if empty. |
| **Production Smoke-Test Script (`smoke_prod.sh`)** | Verification Tool | `[VERIFIED]` | Verified in test environment against running server. |
| **cPanel MultiPHP Selection (PHP 8.3/8.4)** | Hosting Environment | `[NEEDS REAL SERVER]` | Requires manual configuration in cPanel MultiPHP Manager. |
| **cPanel AutoSSL Certificate Issuance** | Hosting SSL | `[NEEDS REAL SERVER]` | Requires running cPanel AutoSSL once DNS resolves to server IP. |
| **cPanel Cron Execution (Scheduler & Queue)** | Shared Hosting Cron | `[NEEDS REAL SERVER]` | Requires adding cron lines in cPanel Cron Jobs interface. |
| **Physical MySQL Service Connection** | Infrastructure | `[NEEDS REAL SERVER]` | Requires live MySQL credentials in production `~/sabapos/.env`. |
| **Physical Thermal Printer / USB Barcode Gun** | Peripheral Hardware | `[NEEDS REAL SERVER]` | Browser print and Web Audio scanner real; physical hardware requires physical counter. |
