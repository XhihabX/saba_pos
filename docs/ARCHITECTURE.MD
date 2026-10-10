# Project Architecture

*This file describes the current state of the system. Update it in place whenever something changes — this is not a log, see CHANGELOG.md for history.*

## Overview
IOT POS (International Office Technology Point of Sale & Cloud ERP) is an international-grade, multi-tenant SaaS Point of Sale (POS) and Enterprise Resource Planning (ERP) platform designed for multi-store chain retail, office tech solutions, food & beverage outlets, and wholesale merchants. It provides centralized tenant administration, chain inventory governance, branch shift audits, and offline-resilient touchscreen cashier POS terminals.

> **Git Branching Strategy**:
> - `main` (Primary Branch): Rebranded enterprise distribution — **IOT (International Office Technology)**.
> - `saba-pos` (Preservation Branch): Pre-rebrand legacy distribution — **Saba POS**.

## Tech Stack
- **Frontend**: Vue 3 (Composition API), Inertia.js 1.x, TailwindCSS 4 (Dual Light/Dark mode & Universal Responsive Breakpoints for Mobile `320px-640px`, Tablet `768px-1024px`, Laptop `1024px-1536px`, and Ultrawide `1536px+`), Lucide Vue Icons, Brand Logo Component (`ApplicationLogo.vue` & `/images/logo.png`)
- **Backend Framework**: Laravel ^13.17 (PHP 8.3+) with Eloquent ORM, Global Query Scopes, Soft Deletes, Permanent Force Delete Purging, and Customer Due Settlement
- **Inertia Adapter**: Inertia Laravel ^3.3, Vue 3.5.13, TailwindCSS 4 (Dual Light/Dark mode & Universal Responsive Breakpoints for Mobile `320px-640px`, Tablet `768px-1024px`, Laptop `1024px-1536px`, and Ultrawide `1536px+`), Lucide Vue Icons, Brand Logo Component (`ApplicationLogo.vue` & `/images/logo.png`)
- **Asset Bundler & Routing**: Vite 5.x, Ziggy Route Engine (with dynamic `APP_URL` auto-healing and `URL::forceRootUrl` guardrails)
- **Deployment & Hosting**: Linux cPanel Shared/VPS hosting (`xhihab.com`) with web setup tool (`public/cpanel_setup.php`) and ZIP packager (`build_cpanel_subdomain_zip.py`)

## Folder Structure
- `app/Http/Controllers/`: Backend controllers handling business logic, transaction blocks, and Inertia response rendering for all 4 portal tiers (`SuperAdmin`, `Merchant`, `StoreManager`, `Cashier POS`).
- `app/Http/Middleware/`: Middleware layers enforcing role-based authorization (`EnsureSuperAdmin`, `EnsureMerchant`, `EnsureStoreManager`, `EnsureActiveSubscription`) and Inertia shared props (`HandleInertiaRequests`).
- `app/Models/`: Eloquent ORM models and relationships (`Tenant`, `Store`, `User`, `Product`, `Stock`, `Order`, `RegisterShift`, `Purchase`, `Supplier`, `Customer`, etc.).
- `app/Models/Scopes/`: Global query scopes (`TenantScope` enforcing tenant-level data isolation across multi-tenant models).
- `app/Traits/`: Reusable model traits (`Tenantable` linking Eloquent models to `TenantScope`).
- `bootstrap/`: Application bootstrap, routing configuration (`bootstrap/app.php`), and cached manifests.
- `database/migrations/`: Database schema definitions for multi-tenancy, stores, products, orders, shifts, soft deletes, and audit logs.
- `database/seeders/`: Seeder scripts (`SabaPosSeeder.php`) for default SaaS tenants, store outlets, products, and role accounts.
- `docs/`: Project documentation including `ARCHITECTURE.md`, `CHANGELOG.md`, and operational guidelines.
- `public/`: Web document root containing compiled Vite assets, `.htaccess`, and web setup helper (`cpanel_setup.php`).
- `resources/js/`: Vue 3 frontend application, components, layouts (`AuthenticatedLayout.vue`), and pages (`resources/js/Pages/*`).
- `routes/`: Web route definitions (`routes/web.php`) organized into 7 functional role and operation layers.

## Feature Modules

### 1. Super Admin Command Center Portal
- **What it does:** Provides complete platform oversight for SaaS owners: merchant tenant management, tenant approvals/rejections, account impersonation, subscription extensions, plan pricing configuration, store governance, global user password resets, platform transaction ledger, global BI analytics, security audit trail, SaaS Recycle Bin with soft-delete restoration, system health telemetry, global announcements broadcast, and platform payment settings.
- **Where it lives:** `app/Http/Controllers/SuperAdminController.php`, `resources/js/Pages/SuperAdmin/*`, `routes/web.php` (`/super-admin/*`)
- **Key design decisions:** Accessible strictly by users with `role === 'super_admin'`. Uses `Inertia::render` with server-side pagination to securely render platform stats.
- **Depends on:** `Tenant`, `Store`, `User`, `SaaSPlan`, `AuditLog`, `Order`, `EnsureSuperAdmin` middleware.
- **Last updated:** 2026-10-04

### 2. Merchant HQ Portal & Chain Governance
- **What it does:** Enables business owners (CEOs) to oversee multi-store chains: executive BI analytics (revenue, COGS, net profit), tenant staff user management, customer CRM with loyalty points & due ledgers, sales order history with thermal receipt previews, SaaS subscription status and renewal payment instructions, and tenant store settings.
- **Where it lives:** `app/Http/Controllers/MerchantController.php`, `resources/js/Pages/Merchant/*`, `routes/web.php` (`/merchant/*`)
- **Key design decisions:** Protected by `EnsureMerchant` and `EnsureActiveSubscription` middleware. All database queries automatically scoped to tenant ID.
- **Depends on:** `Tenant`, `Store`, `User`, `Customer`, `Order`, `EnsureMerchant` middleware.
- **Last updated:** 2026-10-04

### 3. Multi-Store Outlet Governance
- **What it does:** Allows merchants to create, configure, update, and manage branch retail stores, store-specific tax rates, active states, and currency symbols.
- **Where it lives:** `app/Http/Controllers/MerchantStoreController.php`, `resources/js/Pages/Merchant/Stores.vue`, `routes/web.php` (`/merchant/stores/*`)
- **Key design decisions:** Scoped per tenant; soft-deleting a store marks `deleted_at` while preserving historical transaction data.
- **Depends on:** `Store`, `Tenant`, `EnsureMerchant` middleware.
- **Last updated:** 2026-10-04

### 4. Store Manager Branch Portal
- **What it does:** Enables branch managers to oversee daily branch operations, register shift audits (cash over/short variance), inter-store stock transfer requests, product return/refund processing, branch operational expenses, and sales quotations.
- **Where it lives:** `app/Http/Controllers/StoreManagerController.php`, `resources/js/Pages/Manager/*`, `routes/web.php` (`/manager/*`)
- **Key design decisions:** Restricted to `store_manager` and `merchant` roles via `EnsureStoreManager` middleware. Scoped to the manager's assigned `store_id`.
- **Depends on:** `Store`, `RegisterShift`, `StockTransfer`, `ProductReturn`, `EnsureStoreManager` middleware.
- **Last updated:** 2026-10-04

### 5. Cashier POS Counter Workstation
- **What it does:** High-speed touchscreen and mouse-free keyboard counter workstation (`F1` Search Focus, `F2` Customer Quick-Add, `F4` Checkout, `F7`/`F8` Park/Resume Order, `ESC` Modal Dismiss) featuring multi-store outlet switcher, active open register shift enforcement guard (`PosController::checkout`), line-item discount inputs, 1-tap quick cash tendering presets (`৳50`, `৳100`, `৳500`, `৳1000`, `৳2000`, `৳5000`), interactive WebRTC camera video viewfinder barcode scanner, high-speed hardware laser gun barcode scan listener, Web Audio API scan beep synthesis (1200Hz counter feedback tone), category filtering, cart discounts, customer selection (disabling credit for guest customers), split/multi-payment checkout (Cash, bKash, Card, Customer Credit), parked order hold/resume modal with customer names, register shift float tracking modal with automatic checkout redirection, supervisor PIN override verification, thermal receipt printing with store logo & cashier name, auto-stock record creation on checkout, non-blocking top toast notification system (`showSyncNotification`), zero native browser window pauses (`alert`/`confirm` eliminated), and strict JSON response offline sync validation preventing unsaved order deletion.
- **Where it lives:** `app/Http/Controllers/PosController.php`, `resources/js/Pages/POS/Terminal.vue`, `resources/js/Components/POS/CheckoutModal.vue`, `resources/js/Components/POS/ReceiptModal.vue`, `routes/web.php` (`/pos/*`, `/demo/pos` redirect)
- **Key design decisions:** Employs DB row locking during checkout stock deduction to prevent inventory race conditions. Strictly enforces open register shifts (`RegisterShift`) prior to completing checkout with automatic modal prompts. Resilient multi-tier store lookup prevents 404 errors. Automatically creates stock records if missing to guarantee smooth checkout. Zero-pause architecture replaces all native browser alert/confirm popups with asynchronous top toast notifications. Full keyboard hotkey map for 100% mouse-free supermarket cashier operations.
- **Depends on:** `Product`, `Stock`, `Order`, `OrderItem`, `RegisterShift`, `ParkedOrder`, `Customer`.
- **Last updated:** 2026-10-06

### 6. Register Shift Float Audit & Reconciliation
- **What it does:** Manages cashier cash drawer float lifecycle: opening shift balance modal, closing physical cash count drawer reconciliation modal, and automatic cash over/short discrepancy calculation.
- **Where it lives:** `app/Http/Controllers/ShiftController.php`, `app/Models/RegisterShift.php`, `resources/js/Pages/POS/Terminal.vue`, `resources/js/Pages/Manager/Shifts.vue`
- **Key design decisions:** Enforces that cashiers have an active open shift before allowing sales checkout in the POS terminal.
- **Depends on:** `RegisterShift`, `User`, `Store`.
- **Last updated:** 2026-10-05

### 7. Multi-Tenant Data Isolation & Query Scoping Engine
- **What it does:** Enforces strict multi-tenant data isolation across all merchant data (stores, products, orders, customers, users, inventory).
- **Where it lives:** `app/Models/Scopes/TenantScope.php`, `app/Traits/Tenantable.php`, `app/Models/*`
- **Key design decisions:** Implemented as an Eloquent Global Query Scope (`TenantScope`). Automatically appends `WHERE tenant_id = X` to queries when `Auth::check()` is true and user is not a `super_admin`.
- **Depends on:** `Illuminate\Database\Eloquent\Scope`, `Illuminate\Support\Facades\Auth`.
- **Last updated:** 2026-10-04

### 8. Product Catalog & Inventory Management
- **What it does:** Handles product creation, SKU generation, barcode values, cost vs selling prices, product categories, measurement units, and store-specific inventory stock quantities.
- **Where it lives:** `app/Http/Controllers/ProductController.php`, `resources/js/Pages/Products/Index.vue`
- **Key design decisions:** Products link to `Category` and `Unit`, with store stock quantities maintained in the `Stock` table (`store_id`, `product_id`, `quantity`).
- **Depends on:** `Product`, `Category`, `Unit`, `Stock`, `Store`.
- **Last updated:** 2026-10-04

### 9. Barcode Label Generator
- **What it does:** Generates multi-format printable barcode labels (Code-128 / EAN-13) formatted for standard thermal label printers.
- **Where it lives:** `app/Http/Controllers/BarcodeController.php`, `resources/js/Pages/Products/Barcodes.vue`
- **Key design decisions:** Renders print-ready CSS grid components with store name, product title, price, and SVG/Canvas barcodes.
- **Depends on:** `Product`, `Store`.
- **Last updated:** 2026-10-04

### 10. Supplier Directory & Inventory Purchases
- **What it does:** Manages vendor profiles, payables ledgers, purchase order entry, and automatic inventory stock augmentation upon purchase order completion.
- **Where it lives:** `app/Http/Controllers/SupplierController.php`, `resources/js/Pages/Merchant/Suppliers.vue`, `Purchases.vue`
- **Key design decisions:** Purchasing items updates supplier due balances and increments store stock inside a `DB::transaction()`.
- **Depends on:** `Supplier`, `Purchase`, `PurchaseItem`, `Stock`.
- **Last updated:** 2026-10-04

### 11. Inter-Store Inventory Transfers
- **What it does:** Manages stock transfers between store branches within a merchant tenant account, atomically deducting stock from the origin store and adding to the destination store.
- **Where it lives:** `app/Http/Controllers/StockTransferController.php`, `resources/js/Pages/Manager/Transfers.vue`
- **Key design decisions:** Executes stock adjustments inside `DB::transaction()` to ensure atomicity and prevent stock loss.
- **Depends on:** `StockTransfer`, `StockTransferItem`, `Stock`, `Store`.
- **Last updated:** 2026-10-04

### 12. Product Returns & Refund Management
- **What it does:** Processes customer product returns, evaluates return condition (resellable vs damaged), restocks valid items, and records refund payment methods.
- **Where it lives:** `app/Http/Controllers/ReturnController.php`, `resources/js/Pages/Manager/Returns.vue`
- **Key design decisions:** Restocks inventory only when return item condition is set to `resellable`.
- **Depends on:** `ProductReturn`, `Order`, `Product`, `Stock`.
- **Last updated:** 2026-10-04

### 13. Operational Store Expense Tracking
- **What it does:** Tracks branch operational expenses (rent, utilities, maintenance, staff wage advances) per store outlet.
- **Where it lives:** `app/Http/Controllers/ExpenseController.php`, `resources/js/Pages/Expenses/Index.vue`
- **Key design decisions:** Expenses are factored directly into the Net Profit calculation in financial BI reports.
- **Depends on:** `Expense`, `Store`.
- **Last updated:** 2026-10-04

### 14. Sales Quotations & Proforma Invoices
- **What it does:** Allows merchants and store managers to create proforma sales quotes and convert approved quotations into active POS orders with 1 click.
- **Where it lives:** `app/Http/Controllers/QuotationController.php`, `resources/js/Pages/Sales/Quotations.vue`
- **Key design decisions:** Quotation conversion automatically creates an `Order` and deducts inventory stock.
- **Depends on:** `Quotation`, `Order`, `Product`, `Stock`.
- **Last updated:** 2026-10-04

### 15. HRM Staff Attendance & Shift Clocking
- **What it does:** Provides staff clock-in / clock-out tracking, calculating total daily shift hours per employee.
- **Where it lives:** `app/Http/Controllers/AttendanceController.php`, `resources/js/Pages/HRM/Attendance.vue`
- **Key design decisions:** Uses single action toggle (`toggleClock`) per user per day.
- **Depends on:** `Attendance`, `User`.
- **Last updated:** 2026-10-04

### 16. Profit & Loss Financial BI Analytics & Real-Time Reporting Engine
- **What it does:** Generates real-time, accurate financial statements calculating gross revenue, cost of goods sold (COGS), operating expenses, tax liabilities, and net profit margins across stores and date ranges.
- **Where it lives:** `app/Http/Controllers/ReportController.php`, `app/Models/DailySalesSummary.php`, `resources/js/Pages/Reports/ProfitLoss.vue`, `resources/js/Pages/Reports/VatReport.vue`
- **Key design decisions:** Financial reporting queries pre-aggregated metrics directly from `daily_sales_summaries` table (`tenant_id`, `store_id`, `date`, `orders_count`, `subtotal`, `tax_amount`, `grand_total`, `cogs`, `refunds`, and payment method breakdowns) backed by covering index `orders_cashier_reporting_v6_idx` (`tenant_id, user_id, created_at, grand_total`). Financial reports operate on 100% current database state with zero caching delay.
- **Depends on:** `DailySalesSummary`, `Order`, `Purchase`, `Expense`, `Store`.
- **Last updated:** 2026-10-10

### 17. Daily Sales Summary Aggregation Engine, Void Audits & Verification Command
- **What it does:** Maintains enterprise daily store totals updated atomically inside sales checkouts (`PosController::checkout`), void sales (`DailySalesSummary::recordVoidSale`), and return transactions (`ReturnController::store`) using database-native cross-platform upserts (`ON DUPLICATE KEY UPDATE` for MySQL, `ON CONFLICT` for SQLite). Includes scheduled automated audit command `php artisan pos:verify-daily-summaries` comparing summary rows against raw `orders` aggregates per tenant/store/day with optional `--fix` mode.
- **Where it lives:** `app/Models/DailySalesSummary.php`, `app/Console/Commands/VerifyDailySalesSummaryCommand.php`, `routes/console.php`, `database/migrations/2026_10_10_000036_create_daily_sales_summaries_table.php`, `app/Console/Commands/BackfillDailySalesSummaryCommand.php`
- **Key design decisions:** Atomically updates sale revenue, taxes, COGS, and payment breakdowns at the final line of sales checkout transactions for minimum lock holding time. Historical order datasets are backfilled via `php artisan pos:backfill-daily-sales-summary`. Nightly schedule runs integrity verification at 02:00.
- **Depends on:** `PosController`, `ReturnController`, `DailySalesSummary`, `VerifyDailySalesSummaryCommand`, `DailySalesSummaryIntegrityTest`.
- **Last updated:** 2026-10-10

### 18. Public SaaS Marketing Landing Page
- **What it does:** Public landing page introducing Saba POS features, pricing tiers, interactive currency selector, FAQ accordion, and link to POS live demo.
- **Where it lives:** `app/Http/Controllers/LandingController.php`, `resources/js/Pages/Landing.vue`
- **Key design decisions:** Completely accessible without authentication; includes sandbox POS counter demo link (`/demo/pos`).
- **Depends on:** `LandingController`, `SaaSPlan`.
- **Last updated:** 2026-10-04

### 19. Inventory Stock Adjustments & Waste Audit Management
- **What it does:** Tracks inventory adjustments for damaged goods, expired stock, stolen items, write-offs, and physical inventory audit reconciliations with store-level stock quantity adjustments.
- **Where it lives:** `app/Http/Controllers/StockAdjustmentController.php`, `app/Models/StockAdjustment.php`, `resources/js/Pages/Inventory/Adjustments.vue`
- **Key design decisions:** Atomically updates `Stock` quantities based on adjustment type (`add` vs `sub`) within database transactions.
- **Depends on:** `StockAdjustment`, `Product`, `Stock`, `Store`.
- **Last updated:** 2026-10-05

### 20. Universal Multi-Portal Audit Security Logging Engine
- **What it does:** Provides 100% immutable security audit logging across ALL 4 portals (`super_admin`, `merchant`, `store_manager`, `cashier`), recording action trails for sales checkouts, register shift drawer float reconciliations, supervisor PIN overrides, stock waste write-offs, supplier purchase orders, product returns, customer credit due collections, store settings updates, and staff account management.
- **Where it lives:** `app/Services/AuditLogger.php`, `app/Models/AuditLog.php`, `database/migrations/2026_10_06_000014_add_store_id_and_payload_to_audit_logs_table.php`, `resources/js/Pages/SuperAdmin/AuditLogs.vue`, `resources/js/Pages/Merchant/AuditLogs.vue`, `resources/js/Pages/Manager/AuditLogs.vue`
- **Key design decisions:** Centralized `AuditLogger::log` helper automatically captures `tenant_id`, `store_id`, `user_id`, `user_name`, `action`, `description`, `ip_address`, `user_agent`, and `payload` JSON inside database transactions.
- **Depends on:** `AuditLog`, `Tenant`, `Store`, `User`, `Auth`.
- **Last updated:** 2026-10-06

### 21. Hybrid High-Performance CSV Export Engine & Queued Job
- **What it does:** High-performance hybrid CSV export architecture supporting instant inline HTTP streaming for small date ranges (<= 20,000 rows) and background queued job processing (`ExportSalesCsvJob`) with a 30-minute timeout ($1800s$), 1-attempt retry cap, and failure handler writing files to storage with download links and status polling page (`/reports/sales/export-status/{exportId}`) for large exports (> 20,000 rows).
- **Where it lives:** `app/Http/Controllers/PosController.php`, `app/Jobs/ExportSalesCsvJob.php`, `routes/web.php`
- **Key design decisions:** Rewritten without Eloquent ORM overhead using a single optimized SQL query joining `orders`, `stores`, `users`, and `customers`. Uses single-pass `cursor()` streaming, delivering 30-day exports (40,938 rows) in **7.33 seconds** and 366-day exports (500,331 rows) in **116.7 seconds** while maintaining ultra-low peak RAM (26-80 MB).
- **Depends on:** `ExportSalesCsvJob`, `PosController`, `Storage`, `Cache`.
- **Last updated:** 2026-10-10

### 22. Bangladesh Statutory NBR Compliance & Enterprise Regional Module
- **What it does:** Provides 100% statutory NBR Musak-6.3 tax invoice compliance, store BIN number management, dynamic NBR QR codes, non-API Android MFS (bKash/Nagad/Rocket/Upay) notification webhook listener (`/api/v1/mfs-webhook`), TrxID regex verification with duplicate check, Bank Card POS Push-to-terminal bridge simulator, semi-integrated manual card fallback modal, customer credit limits (`credit_limit`), automated SMS payment reminders (`SmsService.php`), FEFO (First-Expired, First-Out) stock batch dispatch (`ProductBatch`), bilingual English & Bengali i18n translation dictionary (`messages.js`), IndexedDB offline sales persistence (`IotPosOfflineDB`), and automated Day-End Z-Reports (`ShiftController::zReport`).
- **Where it lives:** `database/migrations/2026_10_06_000015_create_enterprise_compliance_tables.php`, `app/Services/SmsService.php`, `app/Services/EfdBridgeService.php`, `app/Http/Controllers/Api/MfsWebhookController.php`, `app/Models/MfsTransaction.php`, `app/Models/ProductBatch.php`, `resources/js/i18n/messages.js`, `resources/js/Utils/offlineStorage.js`, `resources/js/Pages/Reports/Vat.vue`, `resources/js/Pages/Reports/Stock.vue`, `resources/js/Pages/Manager/ZReport.vue`, `CheckoutModal.vue`, `ReceiptModal.vue`.
- **Key design decisions:** Atomically deducts FEFO stock batches by earliest `expiry_date` during checkout; enforces customer `credit_limit` guards; provides client-side UUID `idempotency_key` for offline queue sync duplicate prevention.
- **Depends on:** `MfsTransaction`, `ProductBatch`, `Store`, `Customer`, `Order`, `SmsService`, `EfdBridgeService`.
- **Last updated:** 2026-10-07

### 23. Strict Production Security, Secret Isolation & Rate Limiting Engine
- **What it does:** Enforces zero-shortcut production security controls across authentication, secret management, multi-tenancy, and API webhooks:
  1. Deleted legacy seeding/sync web routes (`/setup-database-seed`, `/sync-assets`); seeding restricted strictly to Artisan CLI commands.
  2. Isolated EFD and MFS secrets (`EFD_SECRET_KEY`, `MFS_WEBHOOK_SECRET`) into `.env` / `config/services.php` with zero hardcoded fallback strings.
  3. Hardened `PosController::verifyPin` using `ThrottleFailedPinAttempts` middleware (`throttle.pin`) tracking failed attempts per `user_id` + IP key (`pos-pin-failed:{user}:{ip}`), returning HTTP 429 lockout after 5 failed attempts/min without locking out successful entries.
  4. Enforced strict HTTP 403 authorization for orphan users without a `tenant_id` across all controllers, eliminating default tenant auto-assignment. Removed `?? Store::first()` fallbacks in sales checkout and product returns, returning HTTP 422 validation errors for invalid store IDs.
  5. Refactored `/api/v1/mfs-webhook` in `MfsWebhookController.php` to reject with HTTP 503 if no secret is configured (per-tenant or env). Removed all `app()->environment('testing')` bypasses; timestamp drift validation (>300s) is strictly enforced in all environments.
  6. Enforced production defaults (`APP_ENV=production`, `APP_DEBUG=false`, `QUEUE_CONNECTION=database` in `.env.production.example` and confirmed `.env` git exclusion).
- **Where it lives:** `routes/web.php`, `app/Http/Middleware/ThrottleFailedPinAttempts.php`, `app/Http/Controllers/PosController.php`, `app/Http/Controllers/ReturnController.php`, `app/Http/Controllers/Api/MfsWebhookController.php`, `app/Services/EfdBridgeService.php`, `config/services.php`, `tests/Feature/PinThrottleTest.php`, `tests/Feature/MfsWebhookHardeningTest.php`.
- **Key design decisions:** Pure cryptographic HMAC validation without test environment bypasses; zero auto-fallback tenant or store resolution; failed-attempt rate-limiting middleware.
- **Depends on:** `PosController`, `MfsWebhookController`, `ReturnController`, `EfdBridgeService`, `ThrottleFailedPinAttempts`.
- **Last updated:** 2026-10-10

### 24. Hardened POS Checkout & Stock Exhaustion Concurrency Engine
- **What it does:** Complete concurrency and financial safety overhaul for checkout, stock transfers, and product returns:
  1. Eliminated 100 phantom stock auto-creation (missing stock row defaults strictly to `0.00` quantity).
  2. Created migration `2026_10_07_000021_default_allow_negative_stock_to_false.php` setting `stores.allow_negative_stock` default to `false` and backfilling existing store rows.
  3. Enclosed stock checks and deductions inside DB transactions using `lockForUpdate()` on `Product`, `Stock`, `ProductBatch`, and `StockTransfer`.
  4. Enforced unique `orders.idempotency_key` checking inside transactions via `lockForUpdate()`, returning existing orders for duplicate submissions.
  5. Tested with 20 parallel processes selling the LAST 5 units of a product simultaneously on MySQL: exactly 5 checkouts succeeded, 15 checkouts were rejected with stock validation error, final stock reached exactly `0.00` (NEVER negative!), zero sales were lost or double counted, and zero deadlocks occurred.
- **Where it lives:** `database/migrations/2026_10_07_000021_default_allow_negative_stock_to_false.php`, `app/Http/Controllers/PosController.php`, `app/Http/Controllers/ReturnController.php`, `app/Http/Controllers/ShiftController.php`, `app/Http/Controllers/StockTransferController.php`, `tests/Feature/StockIntegrityConcurrencyTest.php`, `audit/outputs/06_concurrency_test_output.txt`.
- **Key design decisions:** DB row locking prevents concurrency race conditions; integer paisa math eliminates float inaccuracy; strict MFS transaction claiming prevents payment spoofing.
- **Depends on:** `Order`, `OrderItem`, `Stock`, `ProductBatch`, `MfsTransaction`, `RegisterShift`, `ProductReturn`.
- **Last updated:** 2026-10-10

### 25. Configurable Store & Product VAT Rates, Stored Itemized Tax & NBR Compliance Engine
- **What it does:** Configurable multi-tier VAT and NBR statutory tax engine:
  1. Default VAT rate for new stores set to 15.00% via migration `2026_10_07_000028_default_store_tax_rate_to_15.php`, Eloquent `$attributes`, `AuthController`, `MerchantStoreController`, and `SuperAdminController`. Configurable per store (`stores.default_tax_rate`) and per product (`products.vat_rate` allowing 0%, 5%, 7.5%, 10%, 15% overrides).
  2. Configurable tax pricing modes per store (`stores.vat_mode`) and per product (`products.vat_mode`) supporting `'inclusive'` (extracting VAT from price) and `'exclusive'` (adding VAT on top of subtotal). Removed all hardcoded 5% VAT references.
  3. Itemized VAT storage at sale time in `order_items` (`vat_rate`, `vat_amount`). Invoices, thermal receipts, EFD payloads, and VAT reporting use stored itemized VAT rather than recalculated figures.
  4. VAT and Sales reporting (`ReportController::vatReport`) automatically subtract product returns (`ProductReturn`) using exact stored itemized line VAT.
  5. NBR Mushak 6.3 receipts and PDF invoices render Store BIN, Invoice No, Date/Time, itemized VAT rates/amounts, and statutory fields.
  6. EFD module (`EfdBridgeService.php`) removed fake BIN fallback (`'123456789-0000'`). Blocks invoice issuance with HTTP 422 if a store is flagged `is_vat_registered = true` and `bin_number` is empty. Marked as payload generator only in UI and code.
- **Where it lives:** `database/migrations/2026_10_07_000022_add_vat_fields_to_stores_products_and_order_items.php`, `database/migrations/2026_10_07_000028_default_store_tax_rate_to_15.php`, `app/Models/Store.php`, `app/Http/Controllers/PosController.php`, `app/Http/Controllers/ReportController.php`, `app/Services/EfdBridgeService.php`, `resources/views/pdf/invoice.blade.php`, `resources/views/pdf/mushak63.blade.php`, `tests/Feature/StoreVatConfigurationTest.php`.
- **Key design decisions:** Itemized VAT stored permanently at purchase time to maintain audit trail fidelity even if product/store tax rates change later; strict BIN validation prevents unregistered tax collection.
- **Depends on:** `Store`, `Product`, `Order`, `OrderItem`, `ProductReturn`, `EfdBridgeService`.
- **Last updated:** 2026-10-07

### 26. High-Scale SQL Aggregation, Stored COGS, Indexing & Telemetry Engine
- **What it does:** Enterprise scale optimization supporting 500,000+ orders, 1,500,000 order items, and 20,000+ products across 3 store outlets over 1 year of sales history:
  1. Stored `cogs` on `orders` at checkout time and backfilled via migration `2026_10_09_000033_add_cogs_to_orders_table.php`. Profit & Loss COGS uses SQL aggregation (`SUM(cogs)`), eliminating subquery joins and loading all order items into PHP memory.
  2. Created composite covering indexes across `orders`, `order_items`, `order_payments`, `stocks`, `customers`, and `products` (migrations `000032`, `000033`, `000034`, `000035`), enabling zero full table scans (`type: ALL`) and index-only lookups across all 10 POS and reporting endpoints.
  3. Single-pass store aggregations and pre-computed product counts in `ReportController` and `MerchantController`, transforming Eloquent models to lightweight arrays prior to `LengthAwarePaginator` instantiation (dropping Inertia serialization latency from > 5.0s to < 0.4s).
  4. POS workstation payload capped to 50 initial products and 50 initial customers in `PosController::index`. Search API endpoints (`/pos/products/search`, `/pos/customers/search`) search with 300ms client debounce and limit results to 30.
  5. Benchmark command (`BenchmarkPerformanceCommand.php`, `php artisan pos:benchmark {--seed}`) verified on native MySQL 8.4.9 (`sabapos_benchmark` with 500,079 orders) achieving 10/10 PASS (all endpoints max duration <= 1.531s, peak RAM 40 MB <= 256 MB cap, 0 table scans, 0 missing indexes).
- **Where it lives:** `database/migrations/2026_10_09_000032_add_vat_and_reporting_covering_indexes.php`, `database/migrations/2026_10_09_000033_add_cogs_to_orders_table.php`, `database/migrations/2026_10_09_000034_optimize_store_grouping_covering_indexes.php`, `database/migrations/2026_10_09_000035_add_pos_and_stock_covering_indexes.php`, `app/Http/Controllers/PosController.php`, `app/Http/Controllers/MerchantController.php`, `app/Http/Controllers/ReportController.php`, `app/Console/Commands/BenchmarkPerformanceCommand.php`, `audit/outputs/03_benchmark_after.txt`, `audit/outputs/03_explain_before_after.txt`.
- **Key design decisions:** Stored COGS on `orders` eliminates multi-table joins over 1.5M order items; covering indexes provide index-only index scans; array mapping prevents Inertia Blade serialization overhead; database safety guards prevent touching non-test databases.
- **Depends on:** `Order`, `OrderItem`, `Product`, `Customer`, `Stock`, `AuditLog`, `MfsTransaction`, `BenchmarkPerformanceCommand`.
- **Last updated:** 2026-10-10


### 27. Multi-Tenant Data Isolation, 2FA TOTP, Authorization Policies & Security Hardening Engine
- **What it does:** Complete 7-point enterprise security, 2FA TOTP, policy authorization, and multi-tenant isolation engine:
  1. Created migration `2026_10_07_000024_ensure_all_tables_have_tenant_id_and_scope.php` backfilling `tenant_id` and indexing across `Stock`, `OrderItem`, `OrderPayment`, `ProductReturn`, `Purchase`, `PurchaseItem`, `Quotation`, `StockTransfer`, `StockTransferItem`, `Attendance`, and `AuditLog`. Applied `Tenantable` trait (`TenantScope`) across all Eloquent models.
  2. Created automated multi-tenant isolation test suite (`MultiTenantIsolationSecurityTest.php`). Verifies Merchant B receives HTTP 403 / 404 when accessing Merchant A's stores, orders, PDF invoices, Mushak 6.3 templates, returns, checkout, or CSV exports, with zero Tenant A data leaks.
  3. Added TOTP Two-Factor Authentication (`TwoFactorService.php`, `TwoFactorController.php`, migration `2026_10_07_000025_add_two_factor_columns_to_users_table.php`) with 6-digit OTP verification, recovery codes, and setup/challenge flows for `super_admin` and `merchant` roles.
  4. Added explicit Laravel Policy authorization classes (`StorePolicy`, `ProductPolicy`, `OrderPolicy`, `CustomerPolicy`) checking tenant ownership. Registered policies in `AppServiceProvider`.
  5. Mass assignment review: removed `tenant_id`, `role`, and `permissions` from request payload mass assignment paths in controllers (`MerchantController`), enforcing server-assigned session context.
  6. Security dependency audit: executed `composer audit` (0 advisories) and `npm audit` (0 vulnerabilities after adding `shell-quote` overrides).
  7. Security headers & production HTTPS: registered `SecurityHeadersMiddleware.php` (`Strict-Transport-Security`, `X-Content-Type-Options: nosniff`, `X-Frame-Options: SAMEORIGIN`, `X-XSS-Protection: 1; mode=block`, `Referrer-Policy: strict-origin-when-cross-origin`) and forced HTTPS & secure cookies in production (`bootstrap/app.php` & `AppServiceProvider`).
- **Where it lives:** `database/migrations/2026_10_07_000024_ensure_all_tables_have_tenant_id_and_scope.php`, `database/migrations/2026_10_07_000025_add_two_factor_columns_to_users_table.php`, `app/Services/TwoFactorService.php`, `app/Http/Controllers/TwoFactorController.php`, `app/Http/Middleware/SecurityHeadersMiddleware.php`, `app/Policies/*`, `tests/Feature/MultiTenantIsolationSecurityTest.php`.
- **Key design decisions:** 100% database query scoping (`TenantScope`) combined with policy-level authorization checks ensures zero cross-tenant data leakage.
- **Depends on:** `TenantScope`, `TwoFactorService`, `StorePolicy`, `ProductPolicy`, `OrderPolicy`, `CustomerPolicy`, `SecurityHeadersMiddleware`.
- **Last updated:** 2026-10-07

### 28. Enterprise Advanced Engine (Dompdf Exports, Product Variants, Stock Audits, DB Backups, Offline Sync & Branch Analytics)
- **What it does:** Provides advanced enterprise capabilities:
  1. **Dompdf Exports:** Native PDF document rendering for Sales Invoices, Mushak 6.3 Tax Invoices, and Branch Reports using `barryvdh/laravel-dompdf`.
  2. **Multi-Attribute Product Variants:** Migration `2026_10_07_000026_create_product_variants_table.php` adding `ProductVariant` (size/color attributes, custom SKU, barcode, selling price, purchase cost) with dedicated stock tracking (`stocks.variant_id`) and order item recording (`order_items.variant_id`, `variant_name`).
  3. **Stock Audit & Variance Workflow:** Migration `2026_10_07_000027_create_stock_audits_table.php` (`StockAudit`, `StockAuditItem`) supporting count initiation, counted quantity input, financial variance calculations (`variance_qty * unit_cost`), and DB transaction stock adjustments with `AuditLog` logging on approval.
  4. **Automated Database Backup & Restore:** `BackupDatabaseCommand` (`pos:backup`) and `RestoreDatabaseCommand` (`pos:restore`) with gzipped `.gz` archive compression, off-server S3 sync capabilities, 30-backup retention rotation, strict target database safety guards (`RestoreDatabaseCommand` refuses execution unless DB name ends in `_test` or `_benchmark` or `--i-understand-this-overwrites-data` flag is passed), and isolated backup test group (`#[Group('backup')]`).
  5. **Offline Sales Sync Resilience:** Client idempotency key payload queue handling (`orders.idempotency_key`), stock deduction verification, retry deduplication returning original order receipts without double-charging stock.
  6. **Per-Branch Stock & Sales Analytics:** `ReportController::branchReport` aggregating total orders, items sold, gross revenue, VAT collected, COGS, net profit, and inventory valuation per store outlet.
- **Where it lives:** `app/Http/Controllers/PdfExportController.php`, `app/Http/Controllers/StockAuditController.php`, `app/Models/ProductVariant.php`, `app/Models/StockAudit.php`, `app/Models/StockAuditItem.php`, `app/Console/Commands/BackupDatabaseCommand.php`, `app/Console/Commands/RestoreDatabaseCommand.php`, `resources/views/pdf/*`, `tests/Feature/PdfExportTest.php`, `tests/Feature/ProductVariantTest.php`, `tests/Feature/StockAuditTest.php`, `tests/Feature/DatabaseBackupRestoreTest.php`, `tests/Feature/OfflineSalesSyncTest.php`, `tests/Feature/BranchReportsTest.php`.
- **Key design decisions:** Dompdf integration provides crisp thermal and A4 print rendering. Database transaction locking on stock audit approvals and offline checkout sync guarantees zero race conditions or inventory corruption. Restore database command strictly refuses silent overwrites of non-test/non-benchmark databases even if `--force` is provided. Backup/restore test group (`#[Group('backup')]`) is isolated from primary test suite execution.
- **Depends on:** `barryvdh/laravel-dompdf`, `ProductVariant`, `StockAudit`, `StockAuditItem`, `BackupDatabaseCommand`, `RestoreDatabaseCommand`.
- **Last updated:** 2026-10-10

### 29. Production Operations, Telemetry Health, Queue Guards & CI/CD Hardening Engine
- **What it does:** Provides production monitoring, automated CI/CD pipelines, queue guards, and zero-bypass test enforcement:
  1. **Queue Connection Startup Guard:** Configured `QUEUE_CONNECTION=database` in `.env.production.example` and `DEPLOY.md`. Added startup warning checks in `HealthController.php` (`/health`) and `AppServiceProvider.php` (`php artisan about`) warning when `QUEUE_CONNECTION=sync` in production.
  2. **Middleware & Exception Handling Integrity:** Rewrote test suite ensuring zero occurrences of `withoutMiddleware()` or `withoutExceptionHandling()`. All 73 test cases run through authentication, subscription guards, tenant scoping, 2FA, and framework exception handlers.
  3. **GitHub Actions CI/CD Pipeline:** Created [`.github/workflows/tests.yml`](file:///Users/macbookairm1/Downloads/Saba%20POS/.github/workflows/tests.yml) automating linting, SQLite migrations, PHPUnit test suite execution, and Vite asset compilation on every push and pull request. Verified 100% green pass on GitHub Actions (Run #2 for commit `ddd05b6`).
  4. **System Telemetry Health Check Endpoint:** Created [`HealthController.php`](file:///Users/macbookairm1/Downloads/Saba%20POS/app/Http/Controllers/HealthController.php) (`/health`) reporting JSON status for database connectivity latency, cache driver operational status, asynchronous queue driver configuration, storage directory write permissions, PHP memory usage, and response times.
  5. **Daily Log Rotation & Production Drivers:** Configured `config/logging.php` with `daily` stack logging channel (`storage/logs/laravel-YYYY-MM-DD.log`). Set `database` or `redis` as default queue and cache drivers.
  6. **Production Deployment Manual (`DEPLOY.md`):** Created [`DEPLOY.md`](file:///Users/macbookairm1/Downloads/Saba%20POS/DEPLOY.md) detailing production environment variables, cPanel cron setups, supervisor queue daemons (`--timeout=1800`), step-by-step deployment steps, rollback instructions, and emergency disaster recovery protocols.
- **Where it lives:** `app/Http/Controllers/HealthController.php`, `app/Providers/AppServiceProvider.php`, `.github/workflows/tests.yml`, `DEPLOY.md`, `config/logging.php`, `config/queue.php`, `config/cache.php`, `routes/web.php`.
- **Key design decisions:** 100% middleware and exception handling enforcement guarantees that tests mirror real web request execution. Health endpoint provides immediate uptime telemetry for monitoring tools.
- **Depends on:** `HealthController`, `AppServiceProvider`, `DEPLOY.md`, GitHub Actions.

### 30. Pre-Aggregated Daily Sales Summary Engine, Audit Logging & Admin Recalculate Action
- **What it does:** High-performance pre-aggregated financial summary architecture, discrepancy drift logging, admin alerts, and 1-click recalculation workflow:
  1. **Pre-Aggregated Summary Table (`daily_sales_summaries`):** Migration `2026_10_10_000036_create_daily_sales_summaries_table.php` (`DailySalesSummary` model) tracking daily totals per tenant, store, and date: `orders_count`, `subtotal`, `tax_amount`, `grand_total`, `cogs`, `refunds`, and per-payment-method totals.
  2. **Discrepancy Drift Audit & Admin Alerts:** Updated `VerifyDailySalesSummaryCommand.php` (`pos:verify-daily-summaries`) to log every discrepancy via `Log::error()` (tenant, store, date, expected vs actual) and publish admin alerts to `Cache::put('daily_sales_summary_discrepancy_alerts', ...)`. Automated scheduled runs do NOT execute `--fix` silently.
  3. **Admin Manual Recalculation Endpoint:** Added `POST /reports/sales-summary/recalculate` (`ReportController::recalculateDailySummary`) allowing merchant/super-admin users to recalculate flagged dates with 1 click using `DailySalesSummary::recalculateDay()`.
  4. **Corrupted Summary Row Integrity Test:** Created test in `DailySalesSummaryIntegrityTest.php` asserting that corrupted summary rows are detected, logged, published as admin alerts, repaired via manual endpoint trigger, and verified clean.
- **Where it lives:** `app/Models/DailySalesSummary.php`, `app/Console/Commands/VerifyDailySalesSummaryCommand.php`, `app/Http/Controllers/ReportController.php`, `routes/web.php`, `routes/console.php`, `tests/Feature/DailySalesSummaryIntegrityTest.php`.
- **Key design decisions:** Pre-aggregating sales during DB checkout transactions eliminates massive 500k-row table scans; logging and alerting summary drift without auto-fix preserves financial audit trails and provides admin oversight.
- **Depends on:** `DailySalesSummary`, `VerifyDailySalesSummaryCommand`, `ReportController`, `DailySalesSummaryIntegrityTest`.





## Data Model

```
Tenant (1) <---> (N) Store (1) <---> (N) Stock (N) <---> (1) Product
Tenant (1) <---> (N) User (1) <---> (N) Order (1) <---> (N) OrderItem
Tenant (1) <---> (N) Supplier (1) <---> (N) Purchase (1) <---> (N) PurchaseItem
Tenant (1) <---> (N) Customer
Tenant (1) <---> (N) AuditLog
Tenant (1) <---> (N) StockAdjustment
Store  (1) <---> (N) RegisterShift
Store  (1) <---> (N) Expense
Store  (1) <---> (N) StockTransfer (Source / Destination)
```

## Known Issues / Flagged Inferences

- **HRM Module Extensibility**: Attendance tracking is currently limited to basic clock-in/out timestamps; full payroll calculations are flagged for future enhancement.
- **Single Base Currency per Tenant**: Stores can set distinct currency display symbols (`$`, `৳`, `AED`), but overall tenant financial reporting assumes a 1:1 base currency exchange without real-time FX conversions.
- **Tenant Impersonation Session Handling**: Super Admin impersonation uses session key swapping (`impersonated_by`).
