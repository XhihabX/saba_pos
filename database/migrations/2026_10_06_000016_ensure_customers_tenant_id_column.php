<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('customers') && !Schema::hasColumn('customers', 'tenant_id')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->foreignId('tenant_id')->nullable()->after('id');
            });
        }
    }

    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        if (Schema::hasTable('customers')) {
            try {
                Schema::table('customers', function (Blueprint $table) {
                    if (Schema::hasColumn('customers', 'tenant_id')) {
                        try { $table->dropForeign('customers_tenant_id_foreign'); } catch (\Throwable $e) {}
                        $table->dropColumn('tenant_id');
                    }
                });
            } catch (\Throwable $e) {
                // Ignore rollback errors for pre-existing tenant_id columns
            }
        }
        Schema::enableForeignKeyConstraints();
    }
};
