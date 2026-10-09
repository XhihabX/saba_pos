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
            if (!$this->hasIndex('orders', 'orders_store_created_v5_idx')) {
                try {
                    Schema::table('orders', function (Blueprint $table) {
                        $table->index(['tenant_id', 'store_id', 'created_at', 'subtotal', 'tax_amount', 'grand_total', 'cogs'], 'orders_store_created_v5_idx');
                    });
                } catch (\Throwable $e) {}
            }

            if (!$this->hasIndex('orders', 'orders_store_sales_v5_idx')) {
                try {
                    Schema::table('orders', function (Blueprint $table) {
                        $table->index(['tenant_id', 'store_id', 'grand_total'], 'orders_store_sales_v5_idx');
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
                    try { $table->dropIndex('orders_store_created_v5_idx'); } catch (\Throwable $e) {}
                    try { $table->dropIndex('orders_store_sales_v5_idx'); } catch (\Throwable $e) {}
                });
            }
        } catch (\Throwable $e) {
            // Ignore rollback errors
        }
        Schema::enableForeignKeyConstraints();
    }
};
