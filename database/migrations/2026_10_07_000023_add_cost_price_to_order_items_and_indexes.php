<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Add cost_price to order_items
        if (Schema::hasTable('order_items')) {
            Schema::table('order_items', function (Blueprint $table) {
                if (!Schema::hasColumn('order_items', 'cost_price')) {
                    $table->decimal('cost_price', 12, 2)->default(0.00)->after('unit_price');
                }
            });
        }

        // 2. Add performance indexes
        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->index(['tenant_id', 'created_at'], 'orders_tenant_created_idx');
                $table->index(['tenant_id', 'store_id', 'created_at'], 'orders_tenant_store_created_idx');
            });
        }

        if (Schema::hasTable('order_items')) {
            Schema::table('order_items', function (Blueprint $table) {
                $table->index(['order_id', 'product_id'], 'order_items_order_product_idx');
            });
        }

        if (Schema::hasTable('order_payments')) {
            Schema::table('order_payments', function (Blueprint $table) {
                $table->index(['order_id'], 'order_payments_order_idx');
            });
        }

        if (Schema::hasTable('stocks')) {
            Schema::table('stocks', function (Blueprint $table) {
                $table->index(['product_id'], 'stocks_product_idx');
            });
        }

        if (Schema::hasTable('customers')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->index(['tenant_id', 'phone'], 'customers_tenant_phone_idx');
            });
        }

        if (Schema::hasTable('products')) {
            Schema::table('products', function (Blueprint $table) {
                $table->index(['tenant_id', 'is_active'], 'products_tenant_active_idx');
                
                // Tenant-scoped unique SKU constraint
                try {
                    $table->dropUnique('products_sku_unique');
                } catch (\Throwable $e) {
                    // Ignore if unique index name differs
                }
                $table->unique(['tenant_id', 'sku'], 'products_tenant_sku_unique');
            });
        }

        if (Schema::hasTable('audit_logs')) {
            Schema::table('audit_logs', function (Blueprint $table) {
                $table->index(['tenant_id', 'created_at'], 'audit_logs_tenant_created_idx');
            });
        }

        if (Schema::hasTable('mfs_transactions')) {
            Schema::table('mfs_transactions', function (Blueprint $table) {
                $table->index(['tenant_id', 'status'], 'mfs_transactions_tenant_status_idx');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('order_items')) {
            Schema::table('order_items', function (Blueprint $table) {
                $table->dropColumn(['cost_price']);
                $table->dropIndex('order_items_order_product_idx');
            });
        }

        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropIndex('orders_tenant_created_idx');
                $table->dropIndex('orders_tenant_store_created_idx');
            });
        }

        if (Schema::hasTable('order_payments')) {
            Schema::table('order_payments', function (Blueprint $table) {
                $table->dropIndex('order_payments_order_idx');
            });
        }

        if (Schema::hasTable('stocks')) {
            Schema::table('stocks', function (Blueprint $table) {
                $table->dropIndex('stocks_product_idx');
            });
        }

        if (Schema::hasTable('customers')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->dropIndex('customers_tenant_phone_idx');
            });
        }

        if (Schema::hasTable('products')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropIndex('products_tenant_active_idx');
                $table->dropUnique('products_tenant_sku_unique');
            });
        }

        if (Schema::hasTable('audit_logs')) {
            Schema::table('audit_logs', function (Blueprint $table) {
                $table->dropIndex('audit_logs_tenant_created_idx');
            });
        }

        if (Schema::hasTable('mfs_transactions')) {
            Schema::table('mfs_transactions', function (Blueprint $table) {
                $table->dropIndex('mfs_transactions_tenant_status_idx');
            });
        }
    }
};
