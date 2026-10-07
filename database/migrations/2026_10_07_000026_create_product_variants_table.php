<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->string('name'); // e.g. "Red / XL"
            $table->string('sku')->nullable();
            $table->string('barcode')->nullable();
            $table->decimal('price', 12, 2)->nullable();
            $table->decimal('cost_price', 12, 2)->nullable();
            $table->json('attributes')->nullable(); // e.g. {"color": "Red", "size": "XL"}
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'sku']);
            $table->index(['tenant_id', 'barcode']);
            $table->index(['product_id']);
        });

        if (!Schema::hasColumn('stocks', 'variant_id')) {
            try {
                Schema::table('stocks', function (Blueprint $table) {
                    $table->dropUnique(['store_id', 'product_id']);
                });
            } catch (\Throwable $e) {
                // Ignore if unique index does not exist or named differently
            }

            Schema::table('stocks', function (Blueprint $table) {
                $table->foreignId('variant_id')->nullable()->constrained('product_variants')->onDelete('cascade');
                $table->unique(['store_id', 'product_id', 'variant_id'], 'stocks_store_product_variant_unique');
            });
        }

        if (!Schema::hasColumn('order_items', 'variant_id')) {
            Schema::table('order_items', function (Blueprint $table) {
                $table->foreignId('variant_id')->nullable()->constrained('product_variants')->onDelete('set null');
                $table->string('variant_name')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropForeign(['variant_id']);
            $table->dropColumn(['variant_id', 'variant_name']);
        });

        Schema::table('stocks', function (Blueprint $table) {
            $table->dropUnique('stocks_store_product_variant_unique');
            $table->dropForeign(['variant_id']);
            $table->dropColumn('variant_id');
        });

        Schema::dropIfExists('product_variants');
    }
};
