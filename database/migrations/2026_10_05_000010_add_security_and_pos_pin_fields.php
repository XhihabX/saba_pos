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
        if (! Schema::hasColumn('users', 'pos_pin')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('pos_pin')->nullable()->after('password');
            });
        }

        if (! Schema::hasColumn('parked_orders', 'tenant_id')) {
            Schema::table('parked_orders', function (Blueprint $table) {
                $table->foreignId('tenant_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('users', 'pos_pin')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('pos_pin');
            });
        }

        if (Schema::hasColumn('parked_orders', 'tenant_id')) {
            Schema::table('parked_orders', function (Blueprint $table) {
                $table->dropForeign(['tenant_id']);
                $table->dropColumn('tenant_id');
            });
        }
    }
};
