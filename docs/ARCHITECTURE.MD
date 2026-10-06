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

### 16. Profit & Loss Financial BI Analytics
- **What it does:** Generates financial statements calculating gross revenue, cost of goods sold (COGS), operating expenses, and net profit margins across stores and date ranges.
- **Where it lives:** `app/Http/Controllers/ReportController.php`, `resources/js/Pages/Reports/ProfitLoss.vue`
- **Key design decisions:** Aggregates `Order` totals, `Purchase` costs, and `Expense` records per tenant.
- **Depends on:** `Order`, `Purchase`, `Expense`, `Store`.
- **Last updated:** 2026-10-04

### 17. Public SaaS Marketing Landing Page
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

### 21. Universal Client-Side CSV Data Export Engine
- **What it does:** Instant client-side CSV download engine with custom column mappers, automatic RFC-4180 CSV escaping, UTF-8 BOM encoding for Excel/Google Sheets compatibility, and zero server roundtrip latency.
- **Where it lives:** `resources/js/Utils/csvExport.js`, integrated into `Products/Index.vue`, `Merchant/Orders.vue`, `Merchant/Customers.vue`, `Merchant/AuditLogs.vue`, `Manager/AuditLogs.vue`, `SuperAdmin/AuditLogs.vue`, and `Reports/ProfitLoss.vue`.
- **Key design decisions:** Pure browser Blob-based download avoiding heavy PHP server payload rendering and latency.
- **Depends on:** `resources/js/Utils/csvExport.js`.
- **Last updated:** 2026-10-06

### 22. Bangladesh Statutory NBR Compliance & Enterprise Regional Module
- **What it does:** Provides 100% statutory NBR Musak-6.3 tax invoice compliance, store BIN number management, dynamic NBR QR codes, non-API Android MFS (bKash/Nagad/Rocket/Upay) notification webhook listener (`/api/v1/mfs-webhook`), TrxID regex verification with duplicate check, Bank Card POS Push-to-terminal bridge simulator, semi-integrated manual card fallback modal, customer credit limits (`credit_limit`), automated SMS payment reminders (`SmsService.php`), FEFO (First-Expired, First-Out) stock batch dispatch (`ProductBatch`), bilingual English & Bengali i18n translation dictionary (`messages.js`), IndexedDB offline sales persistence (`IotPosOfflineDB`), and automated Day-End Z-Reports (`ShiftController::zReport`).
- **Where it lives:** `database/migrations/2026_10_06_000015_create_enterprise_compliance_tables.php`, `app/Services/SmsService.php`, `app/Services/EfdBridgeService.php`, `app/Http/Controllers/Api/MfsWebhookController.php`, `app/Models/MfsTransaction.php`, `app/Models/ProductBatch.php`, `resources/js/i18n/messages.js`, `resources/js/Utils/offlineStorage.js`, `resources/js/Pages/Reports/Vat.vue`, `resources/js/Pages/Reports/Stock.vue`, `resources/js/Pages/Manager/ZReport.vue`, `CheckoutModal.vue`, `ReceiptModal.vue`.
- **Key design decisions:** Atomically deducts FEFO stock batches by earliest `expiry_date` during checkout; enforces customer `credit_limit` guards; provides client-side UUID `idempotency_key` for offline queue sync duplicate prevention.
- **Depends on:** `MfsTransaction`, `ProductBatch`, `Store`, `Customer`, `Order`, `SmsService`, `EfdBridgeService`.
- **Last updated:** 2026-10-06

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
