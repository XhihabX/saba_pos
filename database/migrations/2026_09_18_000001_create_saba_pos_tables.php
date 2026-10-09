<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Stores / Multi-tenant Outlets
        Schema::create('stores', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->string('vat_number')->nullable();
            $table->string('currency_symbol')->default('৳');
            $table->decimal('default_tax_rate', 5, 2)->default(5.00);
            $table->text('receipt_header')->nullable();
            $table->text('receipt_footer')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 2. Categories
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('image')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 3. Brands
        Schema::create('brands', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('logo')->nullable();
            $table->timestamps();
        });

        // 4. Units
        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // e.g. Piece, Kilogram
            $table->string('short_name'); // e.g. Pc, Kg
            $table->timestamps();
        });

        // 5. Products
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('sku')->unique();
            $table->string('barcode')->nullable()->index();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('purchase_cost', 12, 2)->default(0.00);
            $table->decimal('selling_price', 12, 2)->default(0.00);
            $table->decimal('alert_quantity', 10, 2)->default(5.00);
            $table->boolean('has_serial')->default(false); // Serial/IMEI tracking
            $table->string('image')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 6. Outlet Stock Levels
        Schema::create('stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->decimal('quantity', 12, 2)->default(0.00);
            $table->timestamps();
            $table->unique(['store_id', 'product_id']);
        });

        // 7. Customers
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone')->nullable()->index();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->decimal('due_balance', 12, 2)->default(0.00);
            $table->integer('points')->default(0);
            $table->timestamps();
        });

        // 8. Suppliers
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('company_name');
            $table->string('contact_person')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->decimal('due_balance', 12, 2)->default(0.00);
            $table->timestamps();
        });

        // 9. Purchases
        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->string('purchase_no')->unique();
            $table->foreignId('store_id')->constrained();
            $table->foreignId('supplier_id')->constrained();
            $table->decimal('total_amount', 12, 2)->default(0.00);
            $table->decimal('paid_amount', 12, 2)->default(0.00);
            $table->enum('status', ['received', 'pending', 'ordered'])->default('received');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('purchase_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained();
            $table->decimal('quantity', 12, 2);
            $table->decimal('unit_cost', 12, 2);
            $table->decimal('total_cost', 12, 2);
            $table->timestamps();
        });

        // 10. Orders (POS Sales)
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_no')->unique();
            $table->foreignId('store_id')->constrained();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('subtotal', 12, 2)->default(0.00);
            $table->decimal('discount_amount', 12, 2)->default(0.00);
            $table->decimal('tax_amount', 12, 2)->default(0.00);
            $table->decimal('grand_total', 12, 2)->default(0.00);
            $table->decimal('paid_amount', 12, 2)->default(0.00);
            $table->decimal('change_return', 12, 2)->default(0.00);
            $table->enum('payment_status', ['paid', 'partial', 'due'])->default('paid');
            $table->string('payment_method')->default('cash'); // cash, card, mobile_banking, split, credit
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained();
            $table->string('product_name');
            $table->string('serial_number')->nullable();
            $table->decimal('quantity', 12, 2);
            $table->decimal('unit_price', 12, 2);
            $table->decimal('discount', 12, 2)->default(0.00);
            $table->decimal('total', 12, 2);
            $table->timestamps();
        });

        Schema::create('order_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('payment_method'); // cash, card, mobile_wallet
            $table->decimal('amount', 12, 2);
            $table->string('reference_no')->nullable();
            $table->timestamps();
        });

        // 11. Parked / Held Sales
        Schema::create('parked_orders', function (Blueprint $table) {
            $table->id();
            $table->string('reference_no')->unique();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->string('customer_name')->nullable();
            $table->json('cart_data');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // 12. Expenses
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('category')->default('General');
            $table->decimal('amount', 12, 2);
            $table->date('date');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // 13. Stock Transfers between Stores
        Schema::create('stock_transfers', function (Blueprint $table) {
            $table->id();
            $table->string('transfer_no')->unique();
            $table->foreignId('from_store_id')->constrained('stores');
            $table->foreignId('to_store_id')->constrained('stores');
            $table->enum('status', ['pending', 'completed', 'cancelled'])->default('completed');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('stock_transfer_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_transfer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained();
            $table->decimal('quantity', 12, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('stock_transfer_items');
        Schema::dropIfExists('stock_transfers');
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('parked_orders');
        Schema::dropIfExists('order_payments');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('purchase_items');
        Schema::dropIfExists('purchases');
        Schema::dropIfExists('suppliers');
        Schema::dropIfExists('customers');
        Schema::dropIfExists('stocks');
        Schema::dropIfExists('products');
        Schema::dropIfExists('units');
        Schema::dropIfExists('brands');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('stores');
        Schema::enableForeignKeyConstraints();
    }
};
