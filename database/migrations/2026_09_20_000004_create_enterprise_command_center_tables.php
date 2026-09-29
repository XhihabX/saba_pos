<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Audit Logs for Super Admin & Merchant Security
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('user_name')->default('System');
            $table->string('action'); // e.g. 'tenant_created', 'order_placed', 'shift_closed'
            $table->text('description')->nullable();
            $table->string('ip_address')->nullable();
            $table->timestamps();
        });

        // 2. Product Returns & Refunds Management
        Schema::create('product_returns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignId('product_id')->constrained('products');
            $table->string('product_name');
            $table->decimal('quantity', 12, 2);
            $table->decimal('refund_amount', 12, 2);
            $table->string('refund_method')->default('cash'); // cash, store_credit
            $table->string('reason')->default('Defective / Customer Mind Change');
            $table->string('approved_by')->default('Store Manager');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_returns');
        Schema::dropIfExists('audit_logs');
    }
};
