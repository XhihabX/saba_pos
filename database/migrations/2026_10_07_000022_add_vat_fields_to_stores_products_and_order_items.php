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
        if (Schema::hasTable('stores')) {
            Schema::table('stores', function (Blueprint $table) {
                if (! Schema::hasColumn('stores', 'vat_mode')) {
                    $table->string('vat_mode')->default('exclusive')->after('default_tax_rate');
                }
                if (! Schema::hasColumn('stores', 'is_vat_registered')) {
                    $table->boolean('is_vat_registered')->default(false)->after('vat_mode');
                }
            });
        }

        if (Schema::hasTable('products')) {
            Schema::table('products', function (Blueprint $table) {
                if (! Schema::hasColumn('products', 'vat_rate')) {
                    $table->decimal('vat_rate', 5, 2)->nullable()->after('selling_price');
                }
                if (! Schema::hasColumn('products', 'vat_mode')) {
                    $table->string('vat_mode')->nullable()->after('vat_rate');
                }
            });
        }

        if (Schema::hasTable('order_items')) {
            Schema::table('order_items', function (Blueprint $table) {
                if (! Schema::hasColumn('order_items', 'vat_rate')) {
                    $table->decimal('vat_rate', 5, 2)->default(15.00)->after('discount');
                }
                if (! Schema::hasColumn('order_items', 'vat_amount')) {
                    $table->decimal('vat_amount', 12, 2)->default(0.00)->after('vat_rate');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('stores')) {
            Schema::table('stores', function (Blueprint $table) {
                $table->dropColumn(['vat_mode', 'is_vat_registered']);
            });
        }

        if (Schema::hasTable('products')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropColumn(['vat_rate', 'vat_mode']);
            });
        }

        if (Schema::hasTable('order_items')) {
            Schema::table('order_items', function (Blueprint $table) {
                $table->dropColumn(['vat_rate', 'vat_amount']);
            });
        }
    }
};
