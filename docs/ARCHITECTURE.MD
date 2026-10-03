# Project Architecture

*This file describes the current state of the system. Update it in place whenever something changes — this is not a log, see CHANGELOG.md for history.*

## Overview
Saba POS is an international-grade, multi-tenant SaaS Point of Sale (POS) and Enterprise Resource Planning (ERP) platform designed for multi-store chain retail, food & beverage outlets, and wholesale merchants. It provides centralized tenant administration, chain inventory governance, branch shift audits, and offline-resilient touchscreen cashier POS terminals.

## Tech Stack
- **Frontend**: Vue 3 (Composition API), Inertia.js 1.x, TailwindCSS 3.x, Lucide Vue Icons
- **Backend Framework**: Laravel 11/12 (PHP 8.2+)
- **Database**: MySQL 8.0+ (Shared database, shared schema with `tenant_id` discriminator column)
- **Asset Bundler & Routing**: Vite 5.x, Ziggy Route Engine (with root-relative path normalization)
- **Deployment & Hosting**: Linux cPanel Shared/VPS hosting (`pos.sababilling.net`) with custom web installer (`cpanel_setup.php`) and ZIP builder (`build_cpanel_subdomain_zip.py`)

## Folder Structure
- `app/Http/Controllers/`: Backend controllers handling business logic, transaction blocks, and Inertia response rendering for all 4 portal tiers.
- `app/Http/Middleware/`: Middleware layers enforcing role-based authorization (`EnsureSuperAdmin`, `EnsureMerchant`, `EnsureStoreManager`, `EnsureActiveSubscription`) and Inertia shared props (`HandleInertiaRequests`).
- `app/Models/`: Eloquent ORM models and relationships.
- `app/Models/Scopes/`: Global query scopes (`TenantScope` enforcing tenant-level data isolation).
- `app/Traits/`: Reusable model traits (`Tenantable` linking Eloquent models to `TenantScope`).
- `bootstrap/`: Application bootstrap, routing configuration (`bootstrap/app.php`), and cached manifests.
- `cpanel_dist/`: Compiled production deployment ZIP packages (`subdomain_public.zip`, `sabapos_backend.zip`).
- `database/migrations/`: Database schema definitions for multi-tenancy, stores, products, orders, shifts, and audit logs.
- `database/seeders/`: Seeder scripts (`SabaPosSeeder.php`) for default SaaS tenants, store outlets, products, and role accounts.
- `docs/`: Project documentation including `ARCHITECTURE.MD`, `CHANGELOG.md`, and `ultimate_system_blueprint.md`.
- `public/`: Web document root containing compiled Vite assets, `.htaccess`, and the web setup helper (`cpanel_setup.php`).
- `resources/js/`: Vue 3 frontend application, components, layouts (`AuthenticatedLayout.vue`), and pages (`resources/js/Pages/*`).
- `routes/`: Web route definitions (`routes/web.php`) organized by portal roles.

## Feature Modules

### 1. Super Admin Command Center Portal
- **What it does:** Provides full platform oversight for SaaS platform owners including merchant tenant management, tenant impersonation, subscription approvals, plan pricing configuration, payment verifications (bKash/Nagad/Bank), global store metrics, security audit trail, system telemetry, global announcements, and a recycle bin for suspended accounts.
- **Where it lives:** `app/Http/Controllers/SuperAdminController.php`, `resources/js/Pages/SuperAdmin/*`, `routes/web.php` (`/super-admin/*`)
- **Key design decisions:** Accessible strictly by users with `role === 'super_admin'`. Uses `Inertia::render` to pass paginated platform data without exposure to lower-tier roles.
- **Depends on:** `Tenant`, `Store`, `User`, `SaaSPlan`, `AuditLog`, `Order`, `EnsureSuperAdmin` middleware.
- **Last updated:** 2026-10-03

### 2. Merchant HQ Portal & Chain Governance
- **What it does:** Enables business owners (CEOs) to manage multi-store chains, create branch outlets, manage staff roles, access executive BI analytics (revenue, COGS, net profit), manage subscriptions, manage suppliers, process purchase orders, handle price quotations, track daily expenses, monitor staff attendance, and review Profit & Loss accounting.
- **Where it lives:** `app/Http/Controllers/MerchantController.php`, `MerchantStoreController.php`, `SupplierController.php`, `ReportController.php`, `resources/js/Pages/Merchant/*`, `routes/web.php` (`/merchant/*`)
- **Key design decisions:** Protected by `EnsureMerchant` and `EnsureActiveSubscription` middleware. All queries are scoped to the user's `tenant_id`.
- **Depends on:** `Tenant`, `Store`, `User`, `Supplier`, `Purchase`, `ReportController`, `EnsureMerchant` middleware.
- **Last updated:** 2026-10-03

### 3. Store Manager Portal
- **What it does:** Allows branch managers to monitor daily branch operations, conduct register shift float audits (cash over/short variance), request and accept inter-store stock transfers, process product returns/refunds, issue price quotes, and review branch performance.
- **Where it lives:** `app/Http/Controllers/StoreManagerController.php`, `ShiftController.php`, `StockTransferController.php`, `ReturnController.php`, `resources/js/Pages/Manager/*`, `routes/web.php` (`/manager/*`)
- **Key design decisions:** Restricted to `store_manager` and `merchant` roles via `EnsureStoreManager` middleware. Scoped to the specific assigned `store_id`.
- **Depends on:** `Store`, `RegisterShift`, `StockTransfer`, `ProductReturn`, `EnsureStoreManager` middleware.
- **Last updated:** 2026-10-03

### 4. Cashier POS Counter Workstation
- **What it does:** Touchscreen-optimized cashier counter workstation featuring SKU search, barcode scanning, category filtering, cart modifiers, discounts, customer selection, split/multi-payment checkout (Cash, bKash, Card), parked cart draft/hold functionality, shift register opening/closing float tracking, thermal receipt printing (58mm/80mm), and offline cart resilience.
- **Where it lives:** `app/Http/Controllers/PosController.php`, `ShiftController.php`, `resources/js/Pages/POS/Terminal.vue`, `routes/web.php` (`/pos/*`)
- **Key design decisions:** Employs DB row-level locking (`lockForUpdate()`) during checkout stock deduction to prevent stock over-selling. Uses local storage for offline cart queuing.
- **Depends on:** `Product`, `Stock`, `Order`, `OrderItem`, `RegisterShift`, `ParkedOrder`, `Customer`.
- **Last updated:** 2026-10-03

### 5. Multi-Tenant Data Isolation & Scoping Engine
- **What it does:** Enforces strict data segregation across multi-tenant merchant accounts so no tenant can access another tenant's stores, products, orders, customers, or users.
- **Where it lives:** `app/Models/Scopes/TenantScope.php`, `app/Traits/Tenantable.php`, `app/Models/User.php`, `Store.php`, `Product.php`, `Order.php`, `Stock.php`
- **Key design decisions:** Implemented via Eloquent Global Query Scope (`TenantScope`). Automatically appends `WHERE tenant_id = X` to queries when `Auth::check()` is true and user is not a `super_admin`.
- **Depends on:** `Illuminate\Database\Eloquent\Scope`, `Illuminate\Support\Facades\Auth`.
- **Last updated:** 2026-10-03

### 6. Product Catalog & Barcode Label Generator
- **What it does:** Handles product listings, pricing, SKU assignment, product categories, measurement units, and generates multi-format printable barcode labels (EAN-13 / Code-128) for thermal printers.
- **Where it lives:** `app/Http/Controllers/ProductController.php`, `BarcodeController.php`, `resources/js/Pages/Products/*`
- **Key design decisions:** Products are linked to categories, units, and store-specific stock levels (`Stock` model).
- **Depends on:** `Product`, `Category`, `Unit`, `Stock`, `Store`.
- **Last updated:** 2026-10-03

### 7. Inventory Purchases & Supplier Directory
- **What it does:** Manages vendor profiles, payables ledgers, purchase order entry, and automatic inventory stock augmentation upon purchase order receipt.
- **Where it lives:** `app/Http/Controllers/SupplierController.php`, `resources/js/Pages/Merchant/Suppliers.vue`, `Purchases.vue`
- **Key design decisions:** Purchasing an item updates supplier balance and increments store stock inside a `DB::transaction()`.
- **Depends on:** `Supplier`, `Purchase`, `PurchaseItem`, `Stock`.
- **Last updated:** 2026-10-03

### 8. Web-based cPanel Deployment Engine
- **What it does:** Enables complete database migration, seeding, table wipe recovery, cache clearing, and route optimization directly from a browser without SSH terminal access.
- **Where it lives:** `public/cpanel_setup.php`, `build_cpanel_subdomain_zip.py`
- **Key design decisions:** Disables foreign key checks (`SET FOREIGN_KEY_CHECKS = 0`) during fresh migrations to cleanly drop pre-existing tables on cPanel shared MySQL databases. Automatically purges `bootstrap/cache/routes-v7.php` to prevent stale route locks.
- **Depends on:** `Illuminate\Support\Facades\Artisan`, `DB`, `Schema`.
- **Last updated:** 2026-10-03

## Data Model

```
Tenant (1) <---> (N) Store (1) <---> (N) Stock (N) <---> (1) Product
Tenant (1) <---> (N) User (1) <---> (N) Order (1) <---> (N) OrderItem
Tenant (1) <---> (N) Supplier (1) <---> (N) Purchase
Store  (1) <---> (N) RegisterShift
Tenant (1) <---> (N) AuditLog
```

- **`tenants`**: SaaS business accounts (name, code, subscription_status, mrr_amount, plan_name, expires_at).
- **`stores`**: Branch outlets linked to a tenant (name, code, tax_rate, currency_symbol, is_active).
- **`users`**: User accounts (name, email, password, role, tenant_id, store_id).
- **`products`**: Item catalog (name, sku, barcode, cost_price, selling_price, tenant_id, category_id).
- **`stocks`**: Store-specific inventory quantities (store_id, product_id, quantity).
- **`orders` / `order_items`**: Completed sales transactions and line items.
- **`register_shifts`**: Cashier register drawer float tracking (opening_balance, closing_balance, cash_difference).
- **`saas_plans`**: Pricing tier configurations (Starter, Growth, Enterprise).
- **`audit_logs`**: System security activity logs.

## Known Issues / Tech Debt

- **Lack of Soft Deletes on Core Entities**: Deleting a tenant, store, or product executes a hard SQL `DELETE`. Adding `$table->softDeletes()` will enable restoration of deleted items from the SaaS Recycle Bin. *[Flagged for future enhancement]*
- **Offline Sync Concurrency Over-Selling Risk**: If two offline cashiers sell the last unit of a product locally at the same time, both offline sales succeed locally. When both sync online sequentially, stock decreases below zero (negative inventory). Adding an `allow_negative_stock` configuration check in `PosController::checkout` will flag backorders. *[Flagged for future enhancement]*
- **Login Throttling on Shared Terminal IPs**: `throttle:6,1` limits login attempts to 6 per minute per IP. Fast cashier switching on a shared IP could hit throttling. Increasing to `throttle:15,1` or using IP + Username composite keys is recommended. *[Flagged for future enhancement]*
