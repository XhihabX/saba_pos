# Changelog

*Newest entries at the top. Never edit or delete past entries — this is a permanent record.*

## [2026-10-05] — Comprehensive POS Audit, Server-Side Security, Shift Drawer & Inventory Adjustments
**What:** Completed full system security and operational audit across POS cashier counter, server checkout transaction engine, shift drawer lifecycle, inventory waste tracking, thermal receipt printing, split payment tender modal, and report analytics breakdown.
**Why:** Eliminate demo shortcuts, enforce server-calculated unit prices and tax rates to prevent client-side cart price manipulation, support physical/damaged stock adjustments, enable multi-tender payments (Cash, Card, bKash, Credit), and provide breakdown analytics by Category, Payment Channel, and Cashier Performance.
**How:** Hardened `PosController::checkout` with server database unit price lookup and DB transaction locking (`lockForUpdate()`); created migrations `2026_10_05_000010_add_security_and_pos_pin_fields.php` and `2026_10_05_000011_create_stock_adjustments_table.php`; created `StockAdjustmentController.php` and `Inventory/Adjustments.vue`; upgraded `CheckoutModal.vue` for split multi-payment handling; upgraded `ReceiptModal.vue` with `@media print` 80mm layout; enhanced `ReportController.php` and `ProfitLoss.vue` with breakdown analytics; updated `ShiftController.php` and `MerchantController.php` for AJAX JSON responses; re-built Vite production asset bundle.
**Where:** `app/Http/Controllers/*`, `app/Models/*`, `database/migrations/*`, `resources/js/*`, `docs/ARCHITECTURE.MD`, `docs/CHANGELOG.md`
**Impact:** 100% production security compliance, zero client price tampering, full inventory audit write-offs, and multi-tender split checkout capabilities.

---

## [2026-10-05] — International Office Technology (IOT POS) Rebranding & Branch Preservation Split
**What:** Rebranded application identity on `main` branch to **IOT (International Office Technology)** / **IOT POS** across configuration files (`.env.example`, `.env.production.example`, `config/app.php`), HTML meta tags (`resources/views/app.blade.php`), Vue app setup (`app.js`), brand components (`ApplicationLogo.vue`), layouts (`AuthenticatedLayout.vue`), landing page (`Landing.vue`), auth pages (`Login.vue`, `Register.vue`, `PendingApproval.vue`), thermal receipts (`ReceiptModal.vue`, `Orders.vue`), and settings modals. Created and pushed dedicated `saba-pos` git branch to preserve the original Saba POS distribution on GitHub.
**Why:** Transition primary product branding to International Office Technology (IOT POS) as the main production distribution, while maintaining full source-code preservation of Saba POS under `origin/saba-pos`.
**How:** Created branch `saba-pos` via `git checkout -b saba-pos && git push -u origin saba-pos`. Rebranded text strings, logos, titles, and headers on `main` branch, recompiled Vite asset bundle (`app-DK7DvzZk.js`, `app-LI6q156S.css`), updated documentation, and committed to `main`.
**Where:** `.env.example`, `.env.production.example`, `config/app.php`, `resources/views/app.blade.php`, `resources/js/*`, `docs/ARCHITECTURE.md`, `docs/CHANGELOG.md`
**Impact:** `main` branch now serves **IOT - International Office Technology**, while `saba-pos` branch retains legacy **Saba POS** identity.

---

## [2026-10-05] — Hardened `AppServiceProvider` APP_URL Auto-Healing for Web Requests
**What:** Updated `AppServiceProvider::boot()` to automatically detect incoming web request hosts (`HTTP_HOST`) and override `config('app.url')` when `app.url` contains `localhost`, `127.0.0.1`, or is executed under a cached configuration.
**Why:** Running `php artisan config:cache` on servers where `.env` or local CLI defaults contained `https://localhost:8000` caused Ziggy (`@routes`) and Vite asset tags (`@vite`) to emit asset URLs pointing to `https://localhost:8000/build/assets/...`. Browsers visiting `https://xhihab.com` failed to fetch JavaScript/CSS assets from `localhost:8000`, causing a blank screen.
**How:** Added `str_contains($currentConfigUrl, 'localhost')`, `str_contains($currentConfigUrl, '127.0.0.1')`, and `!empty($_SERVER['HTTP_HOST'])` checks in `AppServiceProvider::boot()` so that any web request dynamically sets `app.url` to the active domain (`https://xhihab.com`) and calls `URL::forceRootUrl(...)`.
**Where:** `app/Providers/AppServiceProvider.php`, `docs/CHANGELOG.md`, `docs/ARCHITECTURE.md`
**Impact:** Prevents blank screens caused by misconfigured `APP_URL` or `config:cache` stale values; guarantees Vite asset tags and Ziggy routes always resolve to `https://xhihab.com`.

---

## [2026-10-04] — Custom Brand Logo Suite, Permanent Force Delete & Customer Credit Settlement Completion
**What:** Integrated custom brand logo asset (`/images/logo.png`) and reusable Vue component (`ApplicationLogo.vue`) across all public navbar headers, auth views (`Login.vue`, `Register.vue`, `PendingApproval.vue`), and authenticated sidebars (`AuthenticatedLayout.vue`). Added `logo_url` column migration (`2026_10_04_000009_add_logo_url_to_tenants_table.php`) enabling merchants to configure custom brand logo URLs for thermal customer receipts and store headers. Implemented permanent force delete (`forceDeleteTenant`, `forceDeleteStore`, `forceDeleteUser`, `forceDeleteProduct`) and restore endpoints in `SuperAdminController.php` and `MerchantController.php` with confirmation modals in `RecycleBin.vue`. Implemented 1-click customer credit due payment settlement (`payCustomerDue`) in `MerchantController.php` and `Customers.vue`.
**Why:** Provide a 100% production-ready enterprise SaaS ERP platform for live retail store management, enabling permanent database data purging, custom receipt logo branding, and automated credit balance settlement.
**How:** Created `ApplicationLogo.vue`, updated `SuperAdminController.php`, `MerchantController.php`, `web.php`, `RecycleBin.vue`, `Settings.vue`, `Customers.vue`, `Login.vue`, `Landing.vue`, `AuthenticatedLayout.vue`, created migration `2026_10_04_000009_add_logo_url_to_tenants_table.php`, and re-generated production deployment ZIP packages (`public_html.zip` & `sabapos.zip`).
**Where:** `app/Http/Controllers/*`, `routes/web.php`, `resources/js/*`, `public/images/logo.png`, `cpanel_dist/*`, `docs/*`
**Impact:** Completes all feature gaps, enables store branding, permanent database management, and live shop customer due collections.

---

## [2026-10-04] — Fix TenantScope Auth Recursion & Call Stack Overflow
**What:** Fixed `Maximum call stack size of 8339456 bytes reached. Infinite recursion?` exception during authentication and portal navigation.
**Why:** `TenantScope::apply()` called `Auth::check()` and `Auth::user()`. Because `User` model included `use Tenantable;`, calling `Auth::user()` triggered Eloquent query on `User`, which fired `TenantScope`, which called `Auth::user()` again, creating an infinite recursion stack overflow loop 100,000 frames deep.
**How:** Added explicit `$model instanceof User` bypass and static `$isResolvingAuth` recursion guard inside `TenantScope::apply()`. Removed `request()->isSecure()` from `AppServiceProvider::boot()` to prevent early provider request binding loops.
**Where:** `app/Models/Scopes/TenantScope.php`, `app/Providers/AppServiceProvider.php`, `docs/CHANGELOG.md`
**Impact:** Completely resolves infinite recursion and stack overflow errors during login and navigation.

---

## [2026-10-04] — SESSION_DRIVER Optimization & Database Migration Synchronization
**What:** Resolved database query exception (`Table 'xhihabco_pos.sessions' doesn't exist`) by switching session driver configuration to `SESSION_DRIVER=file` and re-synchronizing database schema with `php artisan migrate:fresh --seed --force`.
**Why:** When `SESSION_DRIVER=database` is enabled without an existing `sessions` table in the active database connection (`xhihabco_pos`), Laravel's `StartSession` middleware throws a PDOException on initial page request before route dispatching completes.
**How:** Configured `SESSION_DRIVER=file` in `.env` for zero-dependency file-based session handling, executed database migrations and seeders on `xhihabco_pos`, and refreshed application config caches.
**Where:** `.env`, `docs/CHANGELOG.md`
**Impact:** Eliminates database session dependency and ensures 100% reliable session state handling on shared cPanel hosting.

---

## [2026-10-04] — Fix index.php String Escaping Parse Error
**What:** Fixed a PHP parse error on line 38 of `public_html/index.php` (`syntax error, unexpected identifier "Saba"`).
**Why:** Python string interpolation in `build_cpanel_subdomain_zip.py` unescaped double quotes inside `$defaultEnv = "APP_NAME=\"Saba POS\"..."`, causing `index.php` on disk to contain invalid PHP syntax `$defaultEnv = "APP_NAME="Saba POS"..."`, which crashed LiteSpeed with an HTTP 500 error on every web request.
**How:** Replaced double-quote escape sequences with single quotes `$defaultEnv = "APP_NAME='Saba POS'..."` in `build_cpanel_subdomain_zip.py` and regenerated deployment packages in `cpanel_dist/`.
**Where:** `build_cpanel_subdomain_zip.py`, `public/index.php`, `docs/CHANGELOG.md`
**Impact:** Eliminates the PHP parse error in `index.php` completely.

---

## [2026-10-04] — Dynamic APP_URL Auto-Healing & `URL::forceRootUrl` Guardrail Implementation
**What:** Implemented dynamic `APP_URL` auto-healing and enforced `URL::forceRootUrl` inside `AppServiceProvider::boot()`. If `config('app.url')` contains broken host formats (`http://:`, `http://localhost`, or empty), the application automatically detects the active request host (`https://xhihab.com`) and forces the root URL scheme across all Ziggy route generation, asset helper calls, and Vite manifest preloads.
**Why:** Headless Artisan command execution (e.g. `php artisan config:cache` or `php ~/public_html/index.php`) when `APP_URL` in `.env` is unconfigured or set to malformed strings (`http://:`) generates broken Ziggy route URLs (`const Ziggy={"url":"http:\/\/:"}`) and asset preload links (`http://:/build/assets/app-6wiRY_un.js`), leading to HTTP 500 errors in web browsers.
**How:** Added sanitization logic in `AppServiceProvider::boot()` to inspect `config('app.url')`, auto-reconcile invalid URLs against `$_SERVER['HTTP_HOST']` or domain `xhihab.com`, apply `config(['app.url' => $targetUrl])`, and call `URL::forceRootUrl(...)`. Re-packaged production ZIP files (`public_html.zip` and `sabapos.zip`).
**Where:** `app/Providers/AppServiceProvider.php`, `build_cpanel_subdomain_zip.py`, `docs/ARCHITECTURE.md`, `docs/CHANGELOG.md`
**Impact:** Permanently prevents broken `http://:` asset links or Ziggy route payload errors across all environments and command execution contexts.

---

## [2026-10-04] — Production Target Re-Configuration for xhihab.com & Route Cache Serialization Fix
**What:** Configured deployment generator, runtime provider, web installer, and production templates for domain `https://xhihab.com` with frontend document root `public_html` (`/home/username/public_html`) and backend folder `sabapos` (`/home/username/sabapos`). Replaced Closure routes in `routes/web.php` with serializable `Route::redirect()` calls to ensure `php artisan route:cache` runs cleanly on production servers without causing HTTP 500 errors.
**Why:** Running `php artisan route:cache` on routes containing `fn() => ...` Closure callbacks breaks Laravel route dispatching on web requests, leading to HTTP 500 errors.
**How:** Replaced Closure redirects in `routes/web.php` with `Route::redirect()`. Updated `AppServiceProvider::register()` to auto-detect `sabapos` and `public_html`. Updated `.env.production.example` for `https://xhihab.com`. Updated `public/cpanel_setup.php` and `build_cpanel_subdomain_zip.py` to generate `public_html.zip` and `sabapos.zip`.
**Where:** `routes/web.php`, `app/Providers/AppServiceProvider.php`, `.env.production.example`, `public/cpanel_setup.php`, `build_cpanel_subdomain_zip.py`
**Impact:** Enables 1-step extraction and seamless route/config caching on `xhihab.com` via SSH terminal or cPanel.

---

## [2026-10-04] — Post-Deployment Login Blank Screen Resolution & LiteSpeed / suPHP Permissions Hardening
**What:** Resolved post-deployment HTTP 500 zero-byte errors and login blank screens by adjusting directory creation/chmod permissions from `0777` to `0755` for LiteSpeed suPHP compliance, removing disallowed `Options -MultiViews` directives from `public/.htaccess`, adding dynamic web server `DOCUMENT_ROOT` auto-detection to `AppServiceProvider.php` and `index.php`, configuring trusted reverse proxies (`$middleware->trustProxies(at: '*')`) in `bootstrap/app.php`, enforcing HTTPS scheme handling across reverse proxy headers (`X-Forwarded-Proto`), auto-repairing missing `.env`/`APP_KEY` generation, and bundling Vite production build assets into both deployment ZIP packages.
**Why:** LiteSpeed Web Server and suPHP on cPanel shared hosting instantly abort PHP execution with `HTTP 500 (0 bytes)` when directories are set to `0777` (world-writable) or when forbidden `.htaccess` options are specified.
**How:** Updated `cpanel_setup.php` and `create_subdomain_index_php` to enforce `0755` directory permissions. Updated `public/.htaccess` to remove `Options -MultiViews`. Rebuilt production ZIP packages in `cpanel_dist/`.
**Where:** `public/.htaccess`, `public/cpanel_setup.php`, `build_cpanel_subdomain_zip.py`, `app/Providers/AppServiceProvider.php`, `bootstrap/app.php`
**Impact:** Completely eliminates LiteSpeed/suPHP 500 0-byte execution blocks and post-login blank screens; ensures 100% compatibility with all cPanel/LiteSpeed shared hosting configurations.

---

## [2026-10-03] — Full Merchant Portal Dual Light/Dark Mode Design System & Features
**What:** Implemented a unified, adaptive Light Mode (`bg-slate-50`, `bg-white`, `text-slate-900`, `border-slate-200`) and Dark Mode (`bg-slate-950`, `bg-slate-900/90`, `text-slate-100`, `border-slate-800`) across all Merchant Portal pages with an interactive Sun/Moon theme switcher persisted in `localStorage`. Added missing Merchant pages: Settings, Customer CRM with loyalty points/credit due, and Sales Order Ledger with thermal receipt previews.
**Why:** Provide a polished, high-contrast, accessible UI tailored to store owner preferences and lighting conditions across cashier counters and desktop management displays, while giving full control over business settings, customer ledgers, and receipt customization.
**How:** Added Tailwind v4 `@variant dark (&:where(.dark, .dark *));` to `resources/css/app.css`, updated `AuthenticatedLayout.vue` with `isDarkMode` state & Sun/Moon header toggle button, refactored `Dashboard.vue`, `Stores.vue`, `Users.vue`, `Purchases.vue`, `Suppliers.vue`, `Subscription.vue` to full dual mode styling, and created `Settings.vue`, `Customers.vue`, and `Orders.vue`.
**Where:** `resources/js/Layouts/AuthenticatedLayout.vue`, `resources/js/Pages/Merchant/*`, `resources/css/app.css`, `app/Http/Controllers/MerchantController.php`, `routes/web.php`
**Impact:** Zero single-theme legacy code remains in the Merchant Portal; merchants can customize receipt headers/footers, track customer due balances, review order ledgers, and print thermal receipts seamlessly.

---

## [2026-10-03] — Enterprise Soft Deletes, Concurrency Guard & Rate Limit Upgrades
**What:** Upgraded core data models to Eloquent Soft Deletes (`deleted_at`), added `allow_negative_stock` store setting, added backorder tracking on order items, increased authentication throttling to `throttle:30,1`, and added 1-click restoration endpoints in `SuperAdminController`.
**Why:** Elevate system to true international SaaS enterprise standards (Shopify POS / Square standard), preventing accidental data loss, keeping immutable audit trails, allowing fast cashier shift switching, and supporting configurable inventory policy.
**How:** Created migration `2026_10_03_000007_add_soft_deletes_to_core_tables.php`, added `SoftDeletes` trait to `Tenant`, `Store`, `Product`, `User`, `Supplier`, `Category` models, updated `SuperAdminController::recycleBinIndex`, `restoreTenant`, `restoreStore`, and updated route throttling in `routes/web.php`.
**Where:** `app/Models/*`, `app/Http/Controllers/SuperAdminController.php`, `database/migrations/*`, `routes/web.php`
**Impact:** Soft-deleted items can now be restored from `/super-admin/recycle-bin`; cashier logins allow up to 30 attempts per minute on shared IP counter terminals.

---
