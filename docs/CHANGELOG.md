# Changelog

*Newest entries at the top. Never edit or delete past entries — this is a permanent record.*

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
