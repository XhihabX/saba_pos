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
        // 1. Order Payments verification status (Auto-verified vs Counter-verified for MFS & Card)
        if (Schema::hasTable('order_payments') && ! Schema::hasColumn('order_payments', 'verification_status')) {
            Schema::table('order_payments', function (Blueprint $table) {
                $table->string('verification_status')->default('not_applicable')->after('reference_no');
            });
        }

        // 2. Product Returns variant support
        if (Schema::hasTable('product_returns')) {
            Schema::table('product_returns', function (Blueprint $table) {
                if (! Schema::hasColumn('product_returns', 'variant_id')) {
                    $table->unsignedBigInteger('variant_id')->nullable()->after('product_id');
                }
                if (! Schema::hasColumn('product_returns', 'variant_name')) {
                    $table->string('variant_name')->nullable()->after('variant_id');
                }
            });
        }

        // 3. Store configurable cashier discount limit
        if (Schema::hasTable('stores') && ! Schema::hasColumn('stores', 'max_cashier_discount_percent')) {
            Schema::table('stores', function (Blueprint $table) {
                $table->decimal('max_cashier_discount_percent', 5, 2)->default(5.00)->after('default_tax_rate');
            });
        }

        // 4. Expense payment source tracking (Register cash vs Bank)
        if (Schema::hasTable('expenses') && ! Schema::hasColumn('expenses', 'payment_source')) {
            Schema::table('expenses', function (Blueprint $table) {
                $table->string('payment_source')->default('register_cash')->after('payment_method');
            });
        }

        // 5. Order Items price override & zero-stock tracking
        if (Schema::hasTable('order_items')) {
            Schema::table('order_items', function (Blueprint $table) {
                if (! Schema::hasColumn('order_items', 'original_price')) {
                    $table->decimal('original_price', 12, 2)->nullable()->after('unit_price');
                }
                if (! Schema::hasColumn('order_items', 'price_overridden')) {
                    $table->boolean('price_overridden')->default(false)->after('original_price');
                }
                if (! Schema::hasColumn('order_items', 'price_override_reason')) {
                    $table->string('price_override_reason')->nullable()->after('price_overridden');
                }
                if (! Schema::hasColumn('order_items', 'zero_stock_sold')) {
                    $table->boolean('zero_stock_sold')->default(false)->after('cost_price');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('order_payments') && Schema::hasColumn('order_payments', 'verification_status')) {
            Schema::table('order_payments', function (Blueprint $table) {
                $table->dropColumn('verification_status');
            });
        }

        if (Schema::hasTable('product_returns')) {
            Schema::table('product_returns', function (Blueprint $table) {
                if (Schema::hasColumn('product_returns', 'variant_name')) {
                    $table->dropColumn('variant_name');
                }
                if (Schema::hasColumn('product_returns', 'variant_id')) {
                    $table->dropColumn('variant_id');
                }
            });
        }

        if (Schema::hasTable('stores') && Schema::hasColumn('stores', 'max_cashier_discount_percent')) {
            Schema::table('stores', function (Blueprint $table) {
                $table->dropColumn('max_cashier_discount_percent');
            });
        }

        if (Schema::hasTable('expenses') && Schema::hasColumn('expenses', 'payment_source')) {
            Schema::table('expenses', function (Blueprint $table) {
                $table->dropColumn('payment_source');
            });
        }

        if (Schema::hasTable('order_items')) {
            Schema::table('order_items', function (Blueprint $table) {
                if (Schema::hasColumn('order_items', 'zero_stock_sold')) {
                    $table->dropColumn('zero_stock_sold');
                }
                if (Schema::hasColumn('order_items', 'price_override_reason')) {
                    $table->dropColumn('price_override_reason');
                }
                if (Schema::hasColumn('order_items', 'price_overridden')) {
                    $table->dropColumn('price_overridden');
                }
                if (Schema::hasColumn('order_items', 'original_price')) {
                    $table->dropColumn('original_price');
                }
            });
        }
    }
};
