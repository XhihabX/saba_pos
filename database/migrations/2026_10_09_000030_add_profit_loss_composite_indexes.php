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
                $table->index(['tenant_id', 'created_at', 'grand_total'], 'orders_tenant_created_gt_idx');
            });
        }

        if (Schema::hasTable('order_items')) {
            Schema::table('order_items', function (Blueprint $table) {
                $table->index(['order_id', 'product_id', 'quantity', 'cost_price'], 'order_items_order_prod_qty_cost_idx');
            });
        }

        if (Schema::hasTable('order_payments')) {
            Schema::table('order_payments', function (Blueprint $table) {
                $table->index(['order_id', 'payment_method', 'amount'], 'order_payments_order_method_amt_idx');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropIndex('orders_tenant_created_gt_idx');
            });
        }

        if (Schema::hasTable('order_items')) {
            Schema::table('order_items', function (Blueprint $table) {
                $table->dropIndex('order_items_order_prod_qty_cost_idx');
            });
        }

        if (Schema::hasTable('order_payments')) {
            Schema::table('order_payments', function (Blueprint $table) {
                $table->dropIndex('order_payments_order_method_amt_idx');
            });
        }
    }
};
