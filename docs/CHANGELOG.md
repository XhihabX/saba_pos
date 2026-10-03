# Changelog

*Newest entries at the top. Never edit or delete past entries — this is a permanent record.*

## [2026-10-03] — Enterprise Soft Deletes, Concurrency Guard & Rate Limit Upgrades
**What:** Upgraded core data models to Eloquent Soft Deletes (`deleted_at`), added `allow_negative_stock` store setting, added backorder tracking on order items, increased authentication throttling to `throttle:30,1`, and added 1-click restoration endpoints in `SuperAdminController`.
**Why:** Elevate system to true international SaaS enterprise standards (Shopify POS / Square standard), preventing accidental data loss, keeping immutable audit trails, allowing fast cashier shift switching, and supporting configurable inventory policy.
**How:** Created migration `2026_10_03_000007_add_soft_deletes_to_core_tables.php`, added `SoftDeletes` trait to `Tenant`, `Store`, `Product`, `User`, `Supplier`, `Category` models, updated `SuperAdminController::recycleBinIndex`, `restoreTenant`, `restoreStore`, and updated route throttling in `routes/web.php`.
**Where:** `app/Models/*`, `app/Http/Controllers/SuperAdminController.php`, `database/migrations/*`, `routes/web.php`
**Impact:** Soft-deleted items can now be restored from `/super-admin/recycle-bin`; cashier logins allow up to 30 attempts per minute on shared IP counter terminals.

---
