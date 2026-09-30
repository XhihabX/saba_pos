<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Tenants Table
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->string('plan_name')->default('Growth Multi-Store'); // Starter, Growth, Enterprise
            $table->enum('subscription_status', ['pending_approval', 'active', 'past_due', 'suspended', 'rejected'])->default('pending_approval');
            $table->decimal('mrr_amount', 12, 2)->default(3999.00);
            $table->string('payment_method')->nullable();
            $table->string('sender_number')->nullable();
            $table->string('transaction_id')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        // 2. Add Role & Tenant ID to Users
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->after('id')->constrained('tenants')->nullOnDelete();
            $table->foreignId('store_id')->nullable()->after('tenant_id')->constrained('stores')->nullOnDelete();
            $table->enum('role', ['super_admin', 'merchant', 'store_manager', 'cashier'])->default('merchant')->after('email');
        });

        // 3. Add Tenant ID to Stores, Products, Orders, Expenses, Categories
        Schema::table('stores', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->after('id')->constrained('tenants')->cascadeOnDelete();
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->after('id')->constrained('tenants')->cascadeOnDelete();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->after('id')->constrained('tenants')->cascadeOnDelete();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->after('id')->constrained('tenants')->cascadeOnDelete();
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->after('id')->constrained('tenants')->cascadeOnDelete();
        });

        // 3.5 Add Tenant ID to missing Core Models
        Schema::table('customers', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->after('id')->constrained('tenants')->cascadeOnDelete();
        });

        Schema::table('suppliers', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->after('id')->constrained('tenants')->cascadeOnDelete();
        });

        Schema::table('brands', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->after('id')->constrained('tenants')->cascadeOnDelete();
        });

        Schema::table('units', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->after('id')->constrained('tenants')->cascadeOnDelete();
        });


        // 4. Register Shifts (Cashier Open/Close Register & Cash Reconciliation)
        Schema::create('register_shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('opening_cash', 12, 2)->default(0.00);
            $table->decimal('closing_cash_counted', 12, 2)->default(0.00);
            $table->decimal('total_cash_sales', 12, 2)->default(0.00);
            $table->decimal('total_card_sales', 12, 2)->default(0.00);
            $table->decimal('total_mobile_sales', 12, 2)->default(0.00);
            $table->decimal('expected_cash', 12, 2)->default(0.00);
            $table->decimal('cash_difference', 12, 2)->default(0.00);
            $table->enum('status', ['open', 'closed'])->default('open');
            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('register_shifts');

        Schema::table('expenses', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
            $table->dropColumn('tenant_id');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
            $table->dropColumn('tenant_id');
        });

        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
            $table->dropColumn('tenant_id');
        });

        Schema::table('brands', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
            $table->dropColumn('tenant_id');
        });

        Schema::table('units', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
            $table->dropColumn('tenant_id');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
            $table->dropColumn('tenant_id');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
            $table->dropColumn('tenant_id');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
            $table->dropColumn('tenant_id');
        });

        Schema::table('stores', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
            $table->dropColumn('tenant_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['store_id']);
            $table->dropForeign(['tenant_id']);
            $table->dropColumn(['role', 'store_id', 'tenant_id']);
        });

        Schema::dropIfExists('tenants');
    }
};
