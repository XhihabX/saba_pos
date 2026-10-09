<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private function hasIndex(string $table, string $indexName): bool
    {
        try {
            $driver = DB::connection()->getDriverName();
            if ($driver === 'sqlite') {
                return false;
            }
            $dbName = DB::connection()->getDatabaseName();
            $result = DB::select("SELECT COUNT(*) as cnt FROM information_schema.statistics WHERE table_schema = ? AND table_name = ? AND index_name = ?", [$dbName, $table, $indexName]);
            return ($result[0]->cnt ?? 0) > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function up(): void
    {
        if (Schema::hasTable('orders')) {
            if (!$this->hasIndex('orders', 'orders_reporting_v3_idx')) {
                try {
                    Schema::table('orders', function (Blueprint $table) {
                        $table->index(['tenant_id', 'created_at', 'store_id', 'user_id', 'payment_status', 'subtotal', 'tax_amount', 'grand_total'], 'orders_reporting_v3_idx');
                    });
                } catch (\Throwable $e) {}
            }
            if (!$this->hasIndex('orders', 'orders_status_perf_v3_idx')) {
                try {
                    Schema::table('orders', function (Blueprint $table) {
                        $table->index(['tenant_id', 'payment_status', 'grand_total'], 'orders_status_perf_v3_idx');
                    });
                } catch (\Throwable $e) {}
            }
        }

        if (Schema::hasTable('order_items')) {
            if (!$this->hasIndex('order_items', 'order_items_cogs_v3_idx')) {
                try {
                    Schema::table('order_items', function (Blueprint $table) {
                        $table->index(['tenant_id', 'order_id', 'product_id', 'quantity', 'cost_price', 'total', 'vat_amount'], 'order_items_cogs_v3_idx');
                    });
                } catch (\Throwable $e) {}
            }
            if (!$this->hasIndex('order_items', 'order_items_cogs_created_v3_idx')) {
                try {
                    Schema::table('order_items', function (Blueprint $table) {
                        $table->index(['tenant_id', 'created_at', 'quantity', 'cost_price', 'total', 'product_id'], 'order_items_cogs_created_v3_idx');
                    });
                } catch (\Throwable $e) {}
            }
        }

        if (Schema::hasTable('order_payments')) {
            if (!$this->hasIndex('order_payments', 'order_payments_reporting_v3_idx')) {
                try {
                    Schema::table('order_payments', function (Blueprint $table) {
                        $table->index(['tenant_id', 'order_id', 'payment_method', 'amount'], 'order_payments_reporting_v3_idx');
                    });
                } catch (\Throwable $e) {}
            }
            if (!$this->hasIndex('order_payments', 'order_payments_created_reporting_v3_idx')) {
                try {
                    Schema::table('order_payments', function (Blueprint $table) {
                        $table->index(['tenant_id', 'created_at', 'payment_method', 'amount'], 'order_payments_created_reporting_v3_idx');
                    });
                } catch (\Throwable $e) {}
            }
        }

        if (Schema::hasTable('products')) {
            if (!$this->hasIndex('products', 'products_tenant_active_id_idx')) {
                try {
                    Schema::table('products', function (Blueprint $table) {
                        $table->index(['tenant_id', 'is_active', 'id'], 'products_tenant_active_id_idx');
                    });
                } catch (\Throwable $e) {}
            }
        }
    }

    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        try {
            if (Schema::hasTable('orders')) {
                Schema::table('orders', function (Blueprint $table) {
                    try { $table->dropIndex('orders_reporting_v3_idx'); } catch (\Throwable $e) {}
                    try { $table->dropIndex('orders_status_perf_v3_idx'); } catch (\Throwable $e) {}
                });
            }

            if (Schema::hasTable('order_items')) {
                Schema::table('order_items', function (Blueprint $table) {
                    try { $table->dropIndex('order_items_cogs_v3_idx'); } catch (\Throwable $e) {}
                    try { $table->dropIndex('order_items_cogs_created_v3_idx'); } catch (\Throwable $e) {}
                });
            }

            if (Schema::hasTable('order_payments')) {
                Schema::table('order_payments', function (Blueprint $table) {
                    try { $table->dropIndex('order_payments_reporting_v3_idx'); } catch (\Throwable $e) {}
                    try { $table->dropIndex('order_payments_created_reporting_v3_idx'); } catch (\Throwable $e) {}
                });
            }

            if (Schema::hasTable('products')) {
                Schema::table('products', function (Blueprint $table) {
                    try { $table->dropIndex('products_tenant_active_id_idx'); } catch (\Throwable $e) {}
                });
            }
        } catch (\Throwable $e) {
            // Ignore rollback index drop errors
        }
        Schema::enableForeignKeyConstraints();
    }
};
