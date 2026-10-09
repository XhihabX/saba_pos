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
        $tables = [
            'stocks',
            'order_items',
            'order_payments',
            'product_returns',
            'purchases',
            'purchase_items',
            'quotations',
            'stock_transfers',
            'stock_transfer_items',
            'attendances',
            'audit_logs',
        ];

        foreach ($tables as $tableName) {
            if (Schema::hasTable($tableName)) {
                Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                    if (!Schema::hasColumn($tableName, 'tenant_id')) {
                        $table->unsignedBigInteger('tenant_id')->nullable()->index()->after('id');
                    }
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tables = [
            'stocks',
            'order_items',
            'order_payments',
            'product_returns',
            'purchases',
            'purchase_items',
            'quotations',
            'stock_transfers',
            'stock_transfer_items',
            'attendances',
            'audit_logs',
        ];

        Schema::disableForeignKeyConstraints();
        foreach ($tables as $tableName) {
            if (Schema::hasTable($tableName)) {
                try {
                    Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                        if (Schema::hasColumn($tableName, 'tenant_id')) {
                            try {
                                $table->dropForeign("{$tableName}_tenant_id_foreign");
                            } catch (\Throwable $e) {
                                // foreign key may not exist
                            }
                            $table->dropColumn('tenant_id');
                        }
                    });
                } catch (\Throwable $e) {
                    // Ignore drop errors for pre-existing foreign-constrained tenant_id columns
                }
            }
        }
        Schema::enableForeignKeyConstraints();
    }
};
