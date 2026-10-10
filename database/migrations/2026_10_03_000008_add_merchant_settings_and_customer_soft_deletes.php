<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            if (! Schema::hasColumn('tenants', 'currency_symbol')) {
                $table->string('currency_symbol')->default('৳')->after('plan_name');
            }
            if (! Schema::hasColumn('tenants', 'default_tax_rate')) {
                $table->decimal('default_tax_rate', 5, 2)->default(5.00)->after('currency_symbol');
            }
            if (! Schema::hasColumn('tenants', 'receipt_header')) {
                $table->text('receipt_header')->nullable()->after('default_tax_rate');
            }
            if (! Schema::hasColumn('tenants', 'receipt_footer')) {
                $table->text('receipt_footer')->nullable()->after('receipt_header');
            }
            if (! Schema::hasColumn('tenants', 'invoice_prefix')) {
                $table->string('invoice_prefix')->default('INV-')->after('receipt_footer');
            }
        });

        Schema::table('customers', function (Blueprint $table) {
            if (! Schema::hasColumn('customers', 'deleted_at')) {
                $table->softDeletes();
            }
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['currency_symbol', 'default_tax_rate', 'receipt_header', 'receipt_footer', 'invoice_prefix']);
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
