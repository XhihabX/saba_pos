<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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
            $result = DB::select('SELECT COUNT(*) as cnt FROM information_schema.statistics WHERE table_schema = ? AND table_name = ? AND index_name = ?', [$dbName, $table, $indexName]);

            return ($result[0]->cnt ?? 0) > 0;
        } catch (Throwable $e) {
            return false;
        }
    }

    public function up(): void
    {
        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                if (! Schema::hasColumn('orders', 'cogs')) {
                    $table->decimal('cogs', 12, 2)->default(0.00)->after('grand_total');
                }
            });

            if (! $this->hasIndex('orders', 'orders_reporting_v4_idx')) {
                try {
                    Schema::table('orders', function (Blueprint $table) {
                        $table->index(['tenant_id', 'created_at', 'store_id', 'user_id', 'payment_status', 'subtotal', 'tax_amount', 'grand_total', 'cogs'], 'orders_reporting_v4_idx');
                    });
                } catch (Throwable $e) {
                }
            }
        }
    }

    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        try {
            if (Schema::hasTable('orders')) {
                Schema::table('orders', function (Blueprint $table) {
                    try {
                        $table->dropIndex('orders_reporting_v4_idx');
                    } catch (Throwable $e) {
                    }
                    if (Schema::hasColumn('orders', 'cogs')) {
                        $table->dropColumn('cogs');
                    }
                });
            }
        } catch (Throwable $e) {
            // Ignore rollback errors
        }
        Schema::enableForeignKeyConstraints();
    }
};
