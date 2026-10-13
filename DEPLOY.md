# Enterprise Production Deployment & Operations Guide (`DEPLOY.md`)

This guide outlines the production deployment procedure, environment configuration variables, cPanel/Linux queue worker & cron scheduler setup, rollback mechanisms, and emergency disaster recovery protocols for **Saba POS / IOT POS**.

---

## 1. System Requirements & Prerequisites

| Requirement | Supported Version |
| :--- | :--- |
| **Operating System** | Linux (Ubuntu 22.04 LTS / Debian 12 / AlmaLinux / cPanel Cloud) |
| **PHP Version** | PHP 8.3 / PHP 8.5 (Required Extensions: `pdo`, `pdo_mysql` / `pdo_sqlite`, `mbstring`, `openssl`, `gd`, `zip`, `xml`, `bcmath`, `curl`) |
| **Database Server** | MySQL 8.0+ / MariaDB 10.6+ / SQLite 3.35+ |
| **Cache & Queue Driver** | Database (`jobs` & `cache` tables) or Redis Server |
| **Web Server** | Nginx / Apache 2.4+ / Litespeed with HTTP/2 and SSL/TLS |
| **Asset Compiler** | Node.js v20+ & NPM v10+ |

---

## 2. Production Environment Configuration (`.env`)

Create or update `/path/to/app/.env` with these production values:

```ini
APP_NAME="Saba POS"
APP_ENV=production
APP_KEY=base64:GENERATE_WITH_ARTISAN_KEY_GENERATE
APP_DEBUG=false
APP_URL=https://your-pos-domain.com

# Database Configuration
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=sabapos_prod
DB_USERNAME=sabapos_user
DB_PASSWORD="SECURE_PRODUCTION_DB_PASSWORD"

# Queue & Cache Drivers (Must NOT be sync or array in production)
QUEUE_CONNECTION=database
CACHE_STORE=database
SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_SECURE_COOKIE=true

# Daily Error Logging
LOG_CHANNEL=stack
LOG_STACK=daily
LOG_LEVEL=info
LOG_DAILY_DAYS=14

# Production Security & SSL
FORCE_HTTPS=true

# Remote Off-Server Backup Storage (Optional S3 Configuration)
AWS_ACCESS_KEY_ID=your_aws_key
AWS_SECRET_ACCESS_KEY=your_aws_secret
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=your-secure-pos-backups-bucket
```

---

## 3. Step-by-Step Production Deployment Procedure

### Step 1: Clone Repository & Set File Permissions
```bash
git clone https://github.com/XhihabX/saba_pos.git /var/www/sabapos
cd /var/www/sabapos

# Set proper webserver directory ownership
chown -R www-data:www-data /var/www/sabapos
chmod -R 775 storage bootstrap/cache
```

### Step 2: Install Composer Production Dependencies
```bash
composer install --no-dev --optimize-autoloader --no-interaction --no-progress
```

### Step 3: Database Migrations & Daily Sales Summary Verification
Execute database schema migrations, backfill historical sales summaries, and verify financial integrity:
```bash
# 1. Run database schema migrations
php artisan migrate --force

# 2. Backfill missing daily sales summaries for historical sales data
php artisan pos:backfill-daily-sales-summary

# 3. Verify zero financial drift between daily_sales_summaries and raw orders aggregates
php artisan pos:verify-daily-summaries
```

### Step 4: Compile Production Frontend Assets (Vite)
```bash
npm ci
npm run build
```

### Step 5: Optimize Framework Caches & Restart Queue Workers
```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
php artisan queue:restart
```

---

## 4. cPanel & Linux Cron Job / Queue Worker Setup

### A. Scheduler Cron Job
Run Laravel scheduler every minute to process automated backups and cleanups.

**cPanel Cron Job Command:**
```bash
* * * * * /usr/local/bin/php /home/username/public_html/artisan schedule:run >> /dev/null 2>&1
```

### B. Background Queue Worker Setup

#### Option 1: cPanel Cron Worker (Runs every 5 minutes)
```bash
*/5 * * * * /usr/local/bin/php /home/username/public_html/artisan queue:work --stop-when-empty --tries=1 --timeout=1800 >> /dev/null 2>&1
```

#### Option 2: Linux Supervisor Process Daemon (`/etc/supervisor/conf.d/sabapos-worker.conf`)
```ini
[program:sabapos-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/sabapos/artisan queue:work database --sleep=3 --tries=1 --timeout=1800 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/sabapos/storage/logs/worker.log
stopwaitsecs=3600
```
Enable supervisor worker:
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start sabapos-worker:*
```

---

## 5. Rollback Procedures

If a newly deployed release introduces critical issues, execute the rollback workflow immediately:

### Step 1: Revert Code to Previous Commit
```bash
git checkout HEAD~1
```

### Step 2: Roll Back Database Migration (If applicable)
```bash
php artisan migrate:rollback --step=1 --force
```

### Step 3: Re-build Frontend Assets & Clear Caches
```bash
npm run build
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan optimize
```

---

## 6. Disaster Recovery Protocol (If Server Dies / Crashes)

If the production server experiences hardware loss or catastrophic failure:

1. **Provision New Server**: Deploy fresh Linux OS instance and install PHP 8.3+, Nginx/Apache, and MySQL/SQLite.
2. **Pull Codebase**: Clone repository to web root.
3. **Restore Database from Latest Backup**:
   - Download newest backup archive from off-server S3 (`backups/pos_db_backup_YYYYMMDD_HHMMSS.sql.gz`) or local storage.
   - Execute restore command:
     ```bash
     php artisan pos:restore pos_db_backup_YYYYMMDD_HHMMSS.sql.gz --force
     ```
4. **Re-generate Production Caches**:
   ```bash
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   ```
5. **Verify Telemetry**:
   Visit `https://your-pos-domain.com/health` to confirm database connectivity, cache driver operational status, storage write permissions, and memory metrics.

---

## 7. Store Inventory & Stock Policies (Shelf Pickup Mode)

Retail store owners must choose an inventory deficit policy appropriate for their business operations:

### A. Default Inventory Safeguard (`allow_negative_stock = false`)
- By default, Saba POS rejects any live checkout attempt if requested product quantities exceed recorded stock on hand (`stores.allow_negative_stock = false`).
- Attempting to sell more than available units returns an HTTP 422 error (`"Insufficient stock for product '...' (Available: X, Requested: Y)"`).
- This protects warehouses and e-commerce stores from phantom stock and overselling.

### B. Shelf Pickup Mode (`shelf_pickup_mode = true`)
- In physical retail stores (supermarkets, convenience stores, pharmacies), customers bring physical items to the counter. Often, stock is physically on shelves before suppliers' delivery invoices have been entered into the software.
- Store owners can enable `shelf_pickup_mode = true` on their store profile.
- **Deficit Recording (Never Clamped):**
  When enabled, both live POS sales and offline queued sync sales decrement inventory into negative numbers to capture the real physical deficit (e.g., 2 in stock, 5 sold $\rightarrow$ updated stock = `-3.00`, never clamped to `0.00`).
- **Audit Tagging & Flagging:**
  The order is tagged with `zero_stock_sold = true` and flagged with `flagged_for_review = true`.
- **Manager Review Workflow:**
  Store managers can view all deficit orders at `/manager/orders/needs-review`. Once supplier deliveries are physically counted and entered via purchase orders or stock adjustments, managers mark the review as resolved.

---

## 8. Mobile Financial Services (MFS) Reconciliation & Shift Controls

Saba POS supports bKash, Nagad, Rocket, and Upay payments at the cash counter with fraud prevention and reconciliation:

### A. Counter-Verified Payment Flow & Supervisor PIN
When telecom SMS delivery is delayed, cashiers may accept MFS payments with customer-provided TrxIDs (`verification_status = counter_verified`).
- **Store-Configured Anti-Fraud Thresholds:**
  - `stores.max_counter_verified_amount` (Default: ৳5,000.00): Counter-verified payments exceeding this amount require a Store Manager supervisor PIN.
  - `stores.max_counter_verified_per_shift` (Default: 10): If a cashier records more than this number of counter-verified payments in a single shift, each subsequent counter-verified payment requires a supervisor PIN.

### B. Late Webhook Auto-Matching
- When the telecom SMS webhook subsequently arrives at `/api/v1/mfs-webhook`, the system automatically matches the TrxID and amount to the pending counter payment, upgrades `verification_status` to `auto_verified`, claims the MFS transaction, and logs an immutable audit trail.

### C. 24-Hour Unmatched Alerts & Email Notifications
- Counter-verified payments that remain unmatched after 24 hours appear in a high-visibility red badge on the Manager MFS Reconciliation page (`/manager/mfs-reconciliation`).
- Run the scheduled console command:
  ```bash
  php artisan pos:check-unmatched-mfs
  ```
  This creates a `discrepancy_alerts` record and emails a `DiscrepancyAlertMail` notification to the tenant owner.

### D. Shift Z-Report MFS Metrics
- Cashier shift closing Z-reports (`/manager/shifts/{id}/z-report`) display both `counter_verified_count` and `counter_verified_total` alongside cash drawer reconciliation metrics.

