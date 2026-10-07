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
        // 1. Add tenant_id to Stock table if missing
        if (Schema::hasTable('stocks') && !Schema::hasColumn('stocks', 'tenant_id')) {
            Schema::table('stocks', function (Blueprint $table) {
                $table->unsignedBigInteger('tenant_id')->nullable()->default(1)->after('id')->index();
            });
        }

        // 2. Add tenant_id to order_items table if missing
        if (Schema::hasTable('order_items') && !Schema::hasColumn('order_items', 'tenant_id')) {
            Schema::table('order_items', function (Blueprint $table) {
                $table->unsignedBigInteger('tenant_id')->nullable()->default(1)->after('id')->index();
            });
        }

        // 3. Add tenant_id to order_payments table if missing
        if (Schema::hasTable('order_payments') && !Schema::hasColumn('order_payments', 'tenant_id')) {
            Schema::table('order_payments', function (Blueprint $table) {
                $table->unsignedBigInteger('tenant_id')->nullable()->default(1)->after('id')->index();
            });
        }

        // 4. Add tenant_id to product_returns table if missing
        if (Schema::hasTable('product_returns') && !Schema::hasColumn('product_returns', 'tenant_id')) {
            Schema::table('product_returns', function (Blueprint $table) {
                $table->unsignedBigInteger('tenant_id')->nullable()->default(1)->after('id')->index();
            });
        }

        // 5. Add tenant_id to purchases table if missing
        if (Schema::hasTable('purchases') && !Schema::hasColumn('purchases', 'tenant_id')) {
            Schema::table('purchases', function (Blueprint $table) {
                $table->unsignedBigInteger('tenant_id')->nullable()->default(1)->after('id')->index();
            });
        }

        // 6. Add tenant_id to quotations table if missing
        if (Schema::hasTable('quotations') && !Schema::hasColumn('quotations', 'tenant_id')) {
            Schema::table('quotations', function (Blueprint $table) {
                $table->unsignedBigInteger('tenant_id')->nullable()->default(1)->after('id')->index();
            });
        }

        // 7. Add tenant_id to stock_transfers table if missing
        if (Schema::hasTable('stock_transfers') && !Schema::hasColumn('stock_transfers', 'tenant_id')) {
            Schema::table('stock_transfers', function (Blueprint $table) {
                $table->unsignedBigInteger('tenant_id')->nullable()->default(1)->after('id')->index();
            });
        }

        // 8. Add tenant_id to attendances table if missing
        if (Schema::hasTable('attendances') && !Schema::hasColumn('attendances', 'tenant_id')) {
            Schema::table('attendances', function (Blueprint $table) {
                $table->unsignedBigInteger('tenant_id')->nullable()->default(1)->after('id')->index();
            });
        }

        // 9. Add indexes for Item 10: orders(tenant_id, created_at), order_items(order_id), stocks(store_id, product_id)
        Schema::table('orders', function (Blueprint $table) {
            $table->index(['tenant_id', 'created_at'], 'orders_tenant_created_at_idx');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->index('order_id', 'order_items_order_id_idx');
        });

        Schema::table('stocks', function (Blueprint $table) {
            $table->index(['store_id', 'product_id'], 'stocks_store_product_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stocks', function (Blueprint $table) {
            $table->dropIndex('stocks_store_product_idx');
            if (Schema::hasColumn('stocks', 'tenant_id')) {
                $table->dropColumn('tenant_id');
            }
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropIndex('order_items_order_id_idx');
            if (Schema::hasColumn('order_items', 'tenant_id')) {
                $table->dropColumn('tenant_id');
            }
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('orders_tenant_created_at_idx');
        });
    }
};
