<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->index(['tenant_id', 'created_at', 'store_id', 'payment_status', 'grand_total'], 'orders_tenant_created_perf_v2_idx');
            });
        }

        if (Schema::hasTable('order_items')) {
            Schema::table('order_items', function (Blueprint $table) {
                $table->index(['tenant_id', 'order_id', 'product_id', 'quantity', 'total', 'cost_price'], 'order_items_tenant_ord_prod_perf_v2_idx');
            });
        }

        if (Schema::hasTable('order_payments')) {
            Schema::table('order_payments', function (Blueprint $table) {
                $table->index(['tenant_id', 'order_id', 'payment_method', 'amount'], 'order_payments_tenant_ord_perf_v2_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        try {
            if (Schema::hasTable('orders')) {
                Schema::table('orders', function (Blueprint $table) {
                    try {
                        $table->dropIndex('orders_tenant_created_perf_v2_idx');
                    } catch (Throwable $e) {
                    }
                });
            }

            if (Schema::hasTable('order_items')) {
                Schema::table('order_items', function (Blueprint $table) {
                    try {
                        $table->dropIndex('order_items_tenant_ord_prod_perf_v2_idx');
                    } catch (Throwable $e) {
                    }
                });
            }

            if (Schema::hasTable('order_payments')) {
                Schema::table('order_payments', function (Blueprint $table) {
                    try {
                        $table->dropIndex('order_payments_tenant_ord_perf_v2_idx');
                    } catch (Throwable $e) {
                    }
                });
            }
        } catch (Throwable $e) {
            // Ignore rollback index drop errors
        }
        Schema::enableForeignKeyConstraints();
    }
};
