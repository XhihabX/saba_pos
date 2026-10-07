<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->foreignId('store_id')->constrained()->onDelete('cascade');
            $table->string('reference_no')->unique();
            $table->enum('status', ['draft', 'in_progress', 'approved', 'cancelled'])->default('draft');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'store_id', 'created_at']);
        });

        Schema::create('stock_audit_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_audit_id')->constrained('stock_audits')->onDelete('cascade');
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->onDelete('cascade');
            $table->integer('expected_qty')->default(0);
            $table->integer('counted_qty')->default(0);
            $table->integer('variance_qty')->default(0); // counted_qty - expected_qty
            $table->decimal('unit_cost', 12, 2)->default(0.00);
            $table->decimal('variance_value', 12, 2)->default(0.00); // variance_qty * unit_cost
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->index(['stock_audit_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_audit_items');
        Schema::dropIfExists('stock_audits');
    }
};
