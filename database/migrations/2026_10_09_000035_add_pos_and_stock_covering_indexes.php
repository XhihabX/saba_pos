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
        // 1. Covering index for customers query in PosController::index
        Schema::table('customers', function (Blueprint $table) {
            $table->index(['tenant_id', 'name'], 'customers_tenant_name_v5_idx');
        });

        // 2. Covering index for product batches in ReportController::stockReport
        Schema::table('product_batches', function (Blueprint $table) {
            $table->index(['tenant_id', 'quantity', 'expiry_date'], 'batches_tenant_qty_expiry_v5_idx');
        });

        // 3. Covering index for products catalog count & active filter
        Schema::table('products', function (Blueprint $table) {
            $table->index(['tenant_id', 'deleted_at', 'is_active'], 'products_tenant_del_active_v5_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::disableForeignKeyConstraints();

        try {
            Schema::table('customers', function (Blueprint $table) {
                $table->dropIndex('customers_tenant_name_v5_idx');
            });
        } catch (Throwable $e) {
        }

        try {
            Schema::table('product_batches', function (Blueprint $table) {
                $table->dropIndex('batches_tenant_qty_expiry_v5_idx');
            });
        } catch (Throwable $e) {
        }

        try {
            Schema::table('products', function (Blueprint $table) {
                $table->dropIndex('products_tenant_del_active_v5_idx');
            });
        } catch (Throwable $e) {
        }

        Schema::enableForeignKeyConstraints();
    }
};
