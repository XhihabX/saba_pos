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
                if (! Schema::hasColumn('stores', 'shelf_pickup_mode')) {
                    $table->boolean('shelf_pickup_mode')->default(false)->after('allow_negative_stock');
                }
                if (! Schema::hasColumn('stores', 'max_counter_verified_amount')) {
                    $table->decimal('max_counter_verified_amount', 12, 2)->default(5000.00)->after('max_cashier_discount_percent');
                }
                if (! Schema::hasColumn('stores', 'max_counter_verified_per_shift')) {
                    $table->unsignedInteger('max_counter_verified_per_shift')->default(10)->after('max_counter_verified_amount');
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
                if (Schema::hasColumn('stores', 'max_counter_verified_per_shift')) {
                    $table->dropColumn('max_counter_verified_per_shift');
                }
                if (Schema::hasColumn('stores', 'max_counter_verified_amount')) {
                    $table->dropColumn('max_counter_verified_amount');
                }
                if (Schema::hasColumn('stores', 'shelf_pickup_mode')) {
                    $table->dropColumn('shelf_pickup_mode');
                }
            });
        }
    }
};
