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
        Schema::create('daily_sales_summaries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('store_id');
            $table->date('date');

            $table->unsignedInteger('orders_count')->default(0);
            $table->decimal('subtotal', 15, 2)->default(0.00);
            $table->decimal('tax_amount', 15, 2)->default(0.00);
            $table->decimal('grand_total', 15, 2)->default(0.00);
            $table->decimal('cogs', 15, 2)->default(0.00);
            $table->decimal('refunds', 15, 2)->default(0.00);

            $table->decimal('cash_total', 15, 2)->default(0.00);
            $table->decimal('card_total', 15, 2)->default(0.00);
            $table->decimal('bkash_total', 15, 2)->default(0.00);
            $table->decimal('nagad_total', 15, 2)->default(0.00);
            $table->decimal('rocket_total', 15, 2)->default(0.00);
            $table->decimal('upay_total', 15, 2)->default(0.00);
            $table->decimal('due_total', 15, 2)->default(0.00);
            $table->decimal('other_total', 15, 2)->default(0.00);

            $table->timestamps();

            $table->unique(['tenant_id', 'store_id', 'date'], 'daily_summary_unique_tenant_store_date');
            $table->index(['tenant_id', 'date', 'store_id'], 'daily_summary_reporting_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_sales_summaries');
    }
};
