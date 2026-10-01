<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\MerchantController;
use App\Http\Controllers\MerchantStoreController;
use App\Http\Controllers\PosController;
use App\Http\Controllers\ProductController;
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
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:6,1');
Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:6,1');
Route::get('/pending-approval', [AuthController::class, 'showPendingApproval'])->middleware('auth')->name('pending.approval');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// 2. Public SaaS Marketing Landing Page & Public Sandbox POS Demo
Route::get('/', [LandingController::class, 'index'])->name('landing');
Route::get('/demo/pos', [PosController::class, 'sandboxDemo'])->name('pos.demo');

// 3. Super Admin Portal Layer (SaaS Platform Owner & CEO)
Route::prefix('super-admin')->middleware(['auth', EnsureSuperAdmin::class])->group(function () {
    Route::get('/', fn() => redirect()->route('superadmin.dashboard'));
    Route::get('/dashboard', [SuperAdminController::class, 'dashboard'])->name('superadmin.dashboard');
    Route::post('/tenants', [SuperAdminController::class, 'storeTenant'])->name('superadmin.tenants.store');
    Route::post('/tenants/{id}/update', [SuperAdminController::class, 'updateTenant'])->name('superadmin.tenants.update');
    Route::post('/tenants/{id}/approve', [SuperAdminController::class, 'approveTenant'])->name('superadmin.tenants.approve');
    Route::post('/tenants/{id}/reject', [SuperAdminController::class, 'rejectTenant'])->name('superadmin.tenants.reject');
    Route::delete('/tenants/{id}', [SuperAdminController::class, 'deleteTenant'])->name('superadmin.tenants.delete');
    Route::post('/tenants/{id}/impersonate', [SuperAdminController::class, 'impersonateTenant'])->name('superadmin.tenants.impersonate');
    Route::post('/tenants/{id}/extend', [SuperAdminController::class, 'extendSubscription'])->name('superadmin.tenants.extend');
    Route::get('/plans', [SuperAdminController::class, 'plansIndex'])->name('superadmin.plans');
    Route::post('/plans', [SuperAdminController::class, 'storePlan'])->name('superadmin.plans.store');
    Route::post('/plans/{id}/update', [SuperAdminController::class, 'updatePlan'])->name('superadmin.plans.update');
    Route::delete('/plans/{id}', [SuperAdminController::class, 'deletePlan'])->name('superadmin.plans.delete');
    Route::get('/stores', [SuperAdminController::class, 'storesIndex'])->name('superadmin.stores');
    Route::post('/stores/{id}/toggle', [SuperAdminController::class, 'toggleStoreStatus'])->name('superadmin.stores.toggle');
    Route::get('/users', [SuperAdminController::class, 'usersIndex'])->name('superadmin.users');
    Route::post('/users', [SuperAdminController::class, 'storeUser'])->name('superadmin.users.store');
    Route::post('/users/{id}/update', [SuperAdminController::class, 'updateUser'])->name('superadmin.users.update');
    Route::delete('/users/{id}', [SuperAdminController::class, 'deleteUser'])->name('superadmin.users.delete');
    Route::post('/users/{id}/reset-password', [SuperAdminController::class, 'resetUserPassword'])->name('superadmin.users.reset-password');
    Route::get('/transactions', [SuperAdminController::class, 'transactionsIndex'])->name('superadmin.transactions');
    Route::get('/analytics', [SuperAdminController::class, 'analyticsIndex'])->name('superadmin.analytics');
    Route::get('/audit-logs', [SuperAdminController::class, 'auditLogsIndex'])->name('superadmin.auditlogs');
    Route::get('/recycle-bin', [SuperAdminController::class, 'recycleBinIndex'])->name('superadmin.recyclebin');
    Route::get('/settings', [SuperAdminController::class, 'settingsIndex'])->name('superadmin.settings');
    Route::post('/settings', [SuperAdminController::class, 'updateSettings'])->name('superadmin.settings.update');
});

// 4. Merchant HQ Portal Layer (Business CEO & Chain Owner)
Route::prefix('merchant')->middleware(['auth', EnsureMerchant::class, EnsureActiveSubscription::class])->group(function () {
    Route::get('/', fn() => redirect()->route('merchant.dashboard'));
    Route::get('/dashboard', [MerchantController::class, 'dashboard'])->name('merchant.dashboard');
    Route::get('/stores', [MerchantStoreController::class, 'index'])->name('merchant.stores');
    Route::post('/stores', [MerchantStoreController::class, 'store'])->name('merchant.stores.store');
    Route::post('/stores/{id}/update', [MerchantStoreController::class, 'update'])->name('merchant.stores.update');
    Route::delete('/stores/{id}', [MerchantStoreController::class, 'delete'])->name('merchant.stores.delete');
    Route::get('/users', [MerchantController::class, 'staffIndex'])->name('merchant.users');
    Route::post('/users', [MerchantController::class, 'storeStaff'])->name('merchant.users.store');
    Route::post('/users/{id}/update', [MerchantController::class, 'updateStaff'])->name('merchant.users.update');
    Route::delete('/users/{id}', [MerchantController::class, 'deleteStaff'])->name('merchant.users.delete');
    Route::get('/suppliers', [SupplierController::class, 'suppliersIndex'])->name('merchant.suppliers');
    Route::post('/suppliers', [SupplierController::class, 'suppliersStore'])->name('merchant.suppliers.store');
    Route::post('/suppliers/{id}/update', [SupplierController::class, 'updateSupplier'])->name('merchant.suppliers.update');
    Route::delete('/suppliers/{id}', [SupplierController::class, 'deleteSupplier'])->name('merchant.suppliers.delete');
    Route::get('/purchases', [SupplierController::class, 'purchasesIndex'])->name('merchant.purchases');
    Route::post('/purchases', [SupplierController::class, 'purchasesStore'])->name('merchant.purchases.store');
    Route::delete('/purchases/{id}', [SupplierController::class, 'deletePurchase'])->name('merchant.purchases.delete');
    Route::get('/subscription', [MerchantController::class, 'subscription'])->name('merchant.subscription');
});

// 5. Store Manager Portal Layer (Branch Operations & Shift Audit)
Route::prefix('manager')->middleware(['auth', EnsureStoreManager::class, EnsureActiveSubscription::class])->group(function () {
    Route::get('/', fn() => redirect()->route('manager.dashboard'));
    Route::get('/dashboard', [StoreManagerController::class, 'dashboard'])->name('manager.dashboard');
    Route::get('/shifts', [StoreManagerController::class, 'shiftReconciliation'])->name('manager.shifts');
    Route::get('/transfers', [StockTransferController::class, 'index'])->name('manager.transfers');
    Route::post('/transfers', [StockTransferController::class, 'store'])->name('manager.transfers.store');
    Route::get('/returns', [ReturnController::class, 'index'])->name('manager.returns');
    Route::post('/returns', [ReturnController::class, 'store'])->name('manager.returns.store');
});

use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\BarcodeController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\QuotationController;

// 6. Cashier Terminal Layer & Operational ERP Operations (Protected)
Route::middleware(['auth', EnsureActiveSubscription::class])->group(function () {
    Route::get('/pos', [PosController::class, 'index'])->name('pos.index');
    Route::post('/pos/checkout', [PosController::class, 'checkout'])->name('pos.checkout');
    Route::post('/pos/park', [PosController::class, 'parkOrder'])->name('pos.park');
    Route::get('/pos/parked-orders', [PosController::class, 'getParkedOrders'])->name('pos.parked');
    Route::delete('/pos/parked-orders/{id}', [PosController::class, 'deleteParkedOrder'])->name('pos.parked.delete');
    Route::post('/pos/verify-pin', [PosController::class, 'verifyPin'])->name('pos.verify-pin');

    // Register Shift Endpoints
    Route::get('/pos/shift/status', [ShiftController::class, 'currentShiftStatus'])->name('pos.shift.status');
    Route::post('/pos/shift/open', [ShiftController::class, 'openShift'])->name('pos.shift.open');
    Route::post('/pos/shift/close', [ShiftController::class, 'closeShift'])->name('pos.shift.close');

    // HRM Staff Attendance & Clock In/Out
    Route::get('/hrm/attendance', [AttendanceController::class, 'index'])->name('hrm.attendance');
    Route::post('/hrm/attendance/toggle', [AttendanceController::class, 'toggleClock'])->name('hrm.attendance.toggle');
});

// 7. Core ERP Operations (Protected for Store Managers & Merchants)
Route::middleware(['auth', EnsureStoreManager::class, EnsureActiveSubscription::class])->group(function () {
    // General ERP Overview & Inventory
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/products', [ProductController::class, 'index'])->name('products.index');
    Route::post('/products', [ProductController::class, 'store'])->name('products.store');
    Route::post('/products/categories', [ProductController::class, 'storeCategory'])->name('products.categories.store');
    Route::post('/products/{id}/update', [ProductController::class, 'update'])->name('products.update');
    Route::delete('/products/{id}', [ProductController::class, 'delete'])->name('products.delete');
    Route::get('/products/barcodes', [BarcodeController::class, 'index'])->name('products.barcodes');
    Route::get('/reports/profit-loss', [ReportController::class, 'profitLoss'])->name('reports.profit-loss');

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

