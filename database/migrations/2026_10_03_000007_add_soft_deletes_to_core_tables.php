<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            if (!Schema::hasColumn('tenants', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        Schema::table('stores', function (Blueprint $table) {
            if (!Schema::hasColumn('stores', 'deleted_at')) {
                $table->softDeletes();
            }
            if (!Schema::hasColumn('stores', 'allow_negative_stock')) {
                $table->boolean('allow_negative_stock')->default(true)->after('is_active');
            }
        });

        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasColumn('products', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        Schema::table('suppliers', function (Blueprint $table) {
            if (!Schema::hasColumn('suppliers', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        Schema::table('categories', function (Blueprint $table) {
            if (!Schema::hasColumn('categories', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        Schema::table('order_items', function (Blueprint $table) {
            if (!Schema::hasColumn('order_items', 'is_backorder')) {
                $table->boolean('is_backorder')->default(false)->after('total_amount');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
        Schema::table('stores', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropColumn('allow_negative_stock');
        });
        Schema::table('products', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
        Schema::table('categories', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('is_backorder');
        });
    }
};
