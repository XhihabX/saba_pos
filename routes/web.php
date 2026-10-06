<?php

use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BarcodeController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\MerchantController;
use App\Http\Controllers\MerchantStoreController;
use App\Http\Controllers\PosController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\QuotationController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ReturnController;
use App\Http\Controllers\ShiftController;
use App\Http\Controllers\StockTransferController;
use App\Http\Controllers\StoreManagerController;
use App\Http\Controllers\SuperAdminController;
use App\Http\Controllers\SupplierController;
use App\Http\Middleware\EnsureActiveSubscription;
use App\Http\Middleware\EnsureMerchant;
use App\Http\Middleware\EnsureStoreManager;
use App\Http\Middleware\EnsureSuperAdmin;
use Illuminate\Support\Facades\Route;

// 1. Authentication Routes
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:30,1');
Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:15,1');
Route::get('/pending-approval', [AuthController::class, 'showPendingApproval'])->middleware('auth')->name('pending.approval');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// 2. Public SaaS Marketing Landing Page & Public Sandbox POS Demo
Route::get('/', [LandingController::class, 'index'])->name('landing');
Route::get('/demo/pos', [PosController::class, 'sandboxDemo'])->name('pos.demo');

// 3. Super Admin Portal Layer (SaaS Platform Owner & CEO)
Route::prefix('super-admin')->middleware(['auth', EnsureSuperAdmin::class])->group(function () {
    Route::redirect('/', '/super-admin/dashboard');
    Route::get('/dashboard', [SuperAdminController::class, 'dashboard'])->name('superadmin.dashboard');
    Route::post('/tenants', [SuperAdminController::class, 'storeTenant'])->name('superadmin.tenants.store');
    Route::post('/tenants/{id}/update', [SuperAdminController::class, 'updateTenant'])->name('superadmin.tenants.update');
    Route::post('/tenants/{id}/approve', [SuperAdminController::class, 'approveTenant'])->name('superadmin.tenants.approve');
    Route::post('/tenants/{id}/reject', [SuperAdminController::class, 'rejectTenant'])->name('superadmin.tenants.reject');
    Route::post('/tenants/{id}/restore', [SuperAdminController::class, 'restoreTenant'])->name('superadmin.tenants.restore');
    Route::delete('/tenants/{id}', [SuperAdminController::class, 'deleteTenant'])->name('superadmin.tenants.delete');
    Route::delete('/tenants/{id}/force', [SuperAdminController::class, 'forceDeleteTenant'])->name('superadmin.tenants.force-delete');
    Route::post('/tenants/{id}/impersonate', [SuperAdminController::class, 'impersonateTenant'])->name('superadmin.tenants.impersonate');
    Route::post('/exit-impersonate', [SuperAdminController::class, 'exitImpersonation'])->name('superadmin.exit-impersonate');
    Route::post('/tenants/{id}/extend', [SuperAdminController::class, 'extendSubscription'])->name('superadmin.tenants.extend');
    Route::get('/plans', [SuperAdminController::class, 'plansIndex'])->name('superadmin.plans');
    Route::post('/plans', [SuperAdminController::class, 'storePlan'])->name('superadmin.plans.store');
    Route::post('/plans/{id}/update', [SuperAdminController::class, 'updatePlan'])->name('superadmin.plans.update');
    Route::delete('/plans/{id}', [SuperAdminController::class, 'deletePlan'])->name('superadmin.plans.delete');
    Route::get('/stores', [SuperAdminController::class, 'storesIndex'])->name('superadmin.stores');
    Route::post('/stores', [SuperAdminController::class, 'storeStore'])->name('superadmin.stores.store');
    Route::post('/stores/{id}/toggle', [SuperAdminController::class, 'toggleStoreStatus'])->name('superadmin.stores.toggle');
    Route::post('/stores/{id}/restore', [SuperAdminController::class, 'restoreStore'])->name('superadmin.stores.restore');
    Route::delete('/stores/{id}/force', [SuperAdminController::class, 'forceDeleteStore'])->name('superadmin.stores.force-delete');
    Route::get('/users', [SuperAdminController::class, 'usersIndex'])->name('superadmin.users');
    Route::post('/users', [SuperAdminController::class, 'storeUser'])->name('superadmin.users.store');
    Route::post('/users/{id}/update', [SuperAdminController::class, 'updateUser'])->name('superadmin.users.update');
    Route::delete('/users/{id}', [SuperAdminController::class, 'deleteUser'])->name('superadmin.users.delete');
    Route::delete('/users/{id}/force', [SuperAdminController::class, 'forceDeleteUser'])->name('superadmin.users.force-delete');
    Route::post('/users/{id}/restore', [SuperAdminController::class, 'restoreUser'])->name('superadmin.users.restore');
    Route::post('/users/{id}/reset-password', [SuperAdminController::class, 'resetUserPassword'])->name('superadmin.users.reset-password');
    Route::delete('/products/{id}/force', [SuperAdminController::class, 'forceDeleteProduct'])->name('superadmin.products.force-delete');
    Route::post('/products/{id}/restore', [SuperAdminController::class, 'restoreProduct'])->name('superadmin.products.restore');
    Route::get('/transactions', [SuperAdminController::class, 'transactionsIndex'])->name('superadmin.transactions');
    Route::get('/analytics', [SuperAdminController::class, 'analyticsIndex'])->name('superadmin.analytics');
    Route::get('/audit-logs', [SuperAdminController::class, 'auditLogsIndex'])->name('superadmin.auditlogs');
    Route::get('/recycle-bin', [SuperAdminController::class, 'recycleBinIndex'])->name('superadmin.recyclebin');
    Route::get('/system-health', [SuperAdminController::class, 'systemHealthIndex'])->name('superadmin.systemhealth');
    Route::get('/announcements', [SuperAdminController::class, 'announcementsIndex'])->name('superadmin.announcements');
    Route::get('/settings', [SuperAdminController::class, 'settingsIndex'])->name('superadmin.settings');
    Route::post('/settings', [SuperAdminController::class, 'updateSettings'])->name('superadmin.settings.update');
});


// 4. Merchant HQ Portal Layer (Business CEO & Chain Owner)
Route::prefix('merchant')->middleware(['auth', EnsureMerchant::class, EnsureActiveSubscription::class])->group(function () {
    Route::redirect('/', '/merchant/dashboard');
    Route::get('/dashboard', [MerchantController::class, 'dashboard'])->name('merchant.dashboard');
    Route::get('/stores', [MerchantStoreController::class, 'index'])->name('merchant.stores');
    Route::post('/stores', [MerchantStoreController::class, 'store'])->name('merchant.stores.store');
    Route::post('/stores/{id}/update', [MerchantStoreController::class, 'update'])->name('merchant.stores.update');
    Route::delete('/stores/{id}', [MerchantStoreController::class, 'delete'])->name('merchant.stores.delete');
    Route::get('/users', [MerchantController::class, 'staffIndex'])->name('merchant.users');
    Route::post('/users', [MerchantController::class, 'storeStaff'])->name('merchant.users.store');
    Route::post('/users/{id}/update', [MerchantController::class, 'updateStaff'])->name('merchant.users.update');
    Route::delete('/users/{id}', [MerchantController::class, 'deleteStaff'])->name('merchant.users.delete');
    Route::delete('/users/{id}/force', [MerchantController::class, 'forceDeleteStaff'])->name('merchant.users.force-delete');
    Route::post('/users/{id}/restore', [MerchantController::class, 'restoreStaff'])->name('merchant.users.restore');
    Route::get('/suppliers', [SupplierController::class, 'suppliersIndex'])->name('merchant.suppliers');
    Route::post('/suppliers', [SupplierController::class, 'suppliersStore'])->name('merchant.suppliers.store');
    Route::post('/suppliers/{id}/update', [SupplierController::class, 'updateSupplier'])->name('merchant.suppliers.update');
    Route::delete('/suppliers/{id}', [SupplierController::class, 'deleteSupplier'])->name('merchant.suppliers.delete');
    Route::get('/purchases', [SupplierController::class, 'purchasesIndex'])->name('merchant.purchases');
    Route::post('/purchases', [SupplierController::class, 'purchasesStore'])->name('merchant.purchases.store');
    Route::delete('/purchases/{id}', [SupplierController::class, 'deletePurchase'])->name('merchant.purchases.delete');
    Route::get('/subscription', [MerchantController::class, 'subscription'])->name('merchant.subscription');
    Route::get('/settings', [MerchantController::class, 'settings'])->name('merchant.settings');
    Route::post('/settings', [MerchantController::class, 'updateSettings'])->name('merchant.settings.update');
    Route::get('/customers', [MerchantController::class, 'customersIndex'])->name('merchant.customers');
    Route::post('/customers', [MerchantController::class, 'storeCustomer'])->name('merchant.customers.store');
    Route::post('/customers/{id}/update', [MerchantController::class, 'updateCustomer'])->name('merchant.customers.update');
    Route::delete('/customers/{id}', [MerchantController::class, 'deleteCustomer'])->name('merchant.customers.delete');
    Route::post('/customers/{id}/pay-due', [MerchantController::class, 'payCustomerDue'])->name('merchant.customers.pay-due');
    Route::get('/orders', [MerchantController::class, 'ordersIndex'])->name('merchant.orders');
    Route::get('/audit-logs', [MerchantController::class, 'auditLogsIndex'])->name('merchant.auditlogs');
});

// 5. Store Manager Portal Layer (Branch Operations & Shift Audit)
Route::prefix('manager')->middleware(['auth', EnsureStoreManager::class, EnsureActiveSubscription::class])->group(function () {
    Route::redirect('/', '/manager/dashboard');
    Route::get('/dashboard', [StoreManagerController::class, 'dashboard'])->name('manager.dashboard');
    Route::get('/shifts', [StoreManagerController::class, 'shiftReconciliation'])->name('manager.shifts');
    Route::get('/transfers', [StockTransferController::class, 'index'])->name('manager.transfers');
    Route::post('/transfers', [StockTransferController::class, 'store'])->name('manager.transfers.store');
    Route::get('/returns', [ReturnController::class, 'index'])->name('manager.returns');
    Route::post('/returns', [ReturnController::class, 'store'])->name('manager.returns.store');
    Route::get('/expenses', [ExpenseController::class, 'index'])->name('manager.expenses');
    Route::get('/quotations', [QuotationController::class, 'index'])->name('manager.quotations');
    Route::get('/reports', [ReportController::class, 'profitLoss'])->name('manager.reports');
    Route::get('/audit-logs', [StoreManagerController::class, 'auditLogsIndex'])->name('manager.auditlogs');
});

// 6. Cashier Terminal Layer & Operational ERP Operations (Protected)
Route::middleware(['auth', EnsureActiveSubscription::class])->group(function () {
    Route::get('/pos', [PosController::class, 'index'])->name('pos.index');
    Route::post('/pos/checkout', [PosController::class, 'checkout'])->name('pos.checkout');
    Route::post('/pos/park', [PosController::class, 'parkOrder'])->name('pos.park');
    Route::get('/pos/parked-orders', [PosController::class, 'getParkedOrders'])->name('pos.parked');
    Route::delete('/pos/parked-orders/{id}', [PosController::class, 'deleteParkedOrder'])->name('pos.parked.delete');
    Route::delete('/pos/parked/{id}', [PosController::class, 'deleteParkedOrder']);
    Route::post('/pos/verify-pin', [PosController::class, 'verifyPin'])->name('pos.verify-pin');

    // Register Shift Endpoints
    Route::get('/pos/shift/status', [ShiftController::class, 'currentShiftStatus'])->name('pos.shift.status');
    Route::post('/pos/shift/open', [ShiftController::class, 'openShift'])->name('pos.shift.open');
    Route::post('/pos/shift/close', [ShiftController::class, 'closeShift'])->name('pos.shift.close');

    // HRM Staff Attendance & Clock In/Out
    Route::get('/hrm/attendance', [AttendanceController::class, 'index'])->name('hrm.attendance');
    Route::post('/hrm/attendance/toggle', [AttendanceController::class, 'toggleClock'])->name('hrm.attendance.toggle');

    // Inline Customer Registration for POS Cashier Workstation
    Route::post('/customers', [MerchantController::class, 'storeCustomer'])->name('customers.store');
});

use App\Http\Controllers\StockAdjustmentController;

// 7. Core ERP Operations (Protected for Store Managers & Merchants)
Route::middleware(['auth', EnsureStoreManager::class, EnsureActiveSubscription::class])->group(function () {
    // General ERP Overview & Inventory
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/products', [ProductController::class, 'index'])->name('products.index');
    Route::post('/products', [ProductController::class, 'store'])->name('products.store');
    Route::post('/products/categories', [ProductController::class, 'storeCategory'])->name('products.categories.store');
    Route::post('/products/brands', [ProductController::class, 'storeBrand'])->name('products.brands.store');
    Route::post('/products/units', [ProductController::class, 'storeUnit'])->name('products.units.store');
    Route::post('/products/{id}/update', [ProductController::class, 'update'])->name('products.update');
    Route::delete('/products/{id}', [ProductController::class, 'delete'])->name('products.delete');
    Route::get('/products/barcodes', [BarcodeController::class, 'index'])->name('products.barcodes');
    Route::get('/inventory/adjustments', [StockAdjustmentController::class, 'index'])->name('inventory.adjustments');
    Route::post('/inventory/adjustments', [StockAdjustmentController::class, 'store'])->name('inventory.adjustments.store');
    Route::post('/customers/{id}/send-sms', [MerchantController::class, 'sendSmsReminder'])->name('merchant.customers.send-sms');
    Route::get('/reports/profit-loss', [ReportController::class, 'profitLoss'])->name('reports.profit-loss');
    Route::get('/reports/vat', [ReportController::class, 'vatReport'])->name('reports.vat');
    Route::get('/reports/stock', [ReportController::class, 'stockReport'])->name('reports.stock');
    Route::get('/manager/shifts/{id}/z-report', [ShiftController::class, 'zReport'])->name('manager.shifts.z-report');

    // Store Expenses Management
    Route::get('/expenses', [ExpenseController::class, 'index'])->name('expenses.index');
    Route::post('/expenses', [ExpenseController::class, 'store'])->name('expenses.store');
    Route::post('/expenses/{id}/update', [ExpenseController::class, 'updateExpense'])->name('expenses.update');
    Route::delete('/expenses/{id}', [ExpenseController::class, 'deleteExpense'])->name('expenses.delete');

    // Sales Quotations & Price Proformas
    Route::get('/sales/quotations', [QuotationController::class, 'index'])->name('sales.quotations');
    Route::post('/sales/quotations', [QuotationController::class, 'store'])->name('sales.quotations.store');
    Route::post('/sales/quotations/{id}/convert', [QuotationController::class, 'convertToOrder'])->name('sales.quotations.convert');
    Route::delete('/sales/quotations/{id}', [QuotationController::class, 'deleteQuotation'])->name('sales.quotations.delete');
});

// 8. Public API Webhook Endpoints
Route::post('/api/v1/mfs-webhook', [\App\Http\Controllers\Api\MfsWebhookController::class, 'handle'])->name('api.mfs-webhook');

