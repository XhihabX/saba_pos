<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add compliance fields to stores table
        Schema::table('stores', function (Blueprint $table) {
            if (! Schema::hasColumn('stores', 'bin_number')) {
                $table->string('bin_number')->nullable()->after('vat_number');
            }
            if (! Schema::hasColumn('stores', 'is_godown')) {
                $table->boolean('is_godown')->default(false)->after('is_active');
            }
            if (! Schema::hasColumn('stores', 'mfs_number')) {
                $table->string('mfs_number')->nullable()->after('phone');
            }
            if (! Schema::hasColumn('stores', 'sms_gateway_url')) {
                $table->string('sms_gateway_url')->nullable()->after('receipt_footer');
            }
            if (! Schema::hasColumn('stores', 'sms_api_key')) {
                $table->string('sms_api_key')->nullable()->after('sms_gateway_url');
            }
        });

        // 2. Add credit_limit to customers table
        Schema::table('customers', function (Blueprint $table) {
            if (! Schema::hasColumn('customers', 'credit_limit')) {
                $table->decimal('credit_limit', 12, 2)->default(50000.00)->after('due_balance');
            }
        });

        // 3. Add idempotency_key to orders table
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'idempotency_key')) {
                $table->string('idempotency_key')->nullable()->unique()->after('invoice_no');
            }
        });

        // 4. MFS Non-API Android Notification Webhook Transactions Table
        if (! Schema::hasTable('mfs_transactions')) {
            Schema::create('mfs_transactions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable()->index();
                $table->string('trx_id')->unique();
                $table->string('sender')->nullable();
                $table->decimal('amount', 12, 2);
                $table->string('gateway')->default('bkash'); // bkash, nagad, rocket, upay
                $table->enum('status', ['unclaimed', 'claimed'])->default('unclaimed');
                $table->unsignedBigInteger('order_id')->nullable();
                $table->timestamps();
            });
        }

        // 5. Product Batches & Expiry (FEFO) Table
        if (! Schema::hasTable('product_batches')) {
            Schema::create('product_batches', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable()->index();
                $table->foreignId('store_id')->constrained()->cascadeOnDelete();
                $table->foreignId('product_id')->constrained()->cascadeOnDelete();
                $table->string('batch_no');
                $table->date('expiry_date')->index();
                $table->decimal('quantity', 12, 2)->default(0.00);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        try {
            Schema::dropIfExists('product_batches');
            Schema::dropIfExists('mfs_transactions');

            if (Schema::hasTable('orders')) {
                Schema::table('orders', function (Blueprint $table) {
                    if (Schema::hasColumn('orders', 'idempotency_key')) {
                        try {
                            $table->dropColumn('idempotency_key');
                        } catch (Throwable $e) {
                        }
                    }
                });
            }

            if (Schema::hasTable('customers')) {
                Schema::table('customers', function (Blueprint $table) {
                    if (Schema::hasColumn('customers', 'credit_limit')) {
                        try {
                            $table->dropColumn('credit_limit');
                        } catch (Throwable $e) {
                        }
                    }
                });
            }

            if (Schema::hasTable('stores')) {
                Schema::table('stores', function (Blueprint $table) {
                    $cols = array_filter(['bin_number', 'is_godown', 'mfs_number', 'sms_gateway_url', 'sms_api_key'], fn ($c) => Schema::hasColumn('stores', $c));
                    if (! empty($cols)) {
                        try {
                            $table->dropColumn($cols);
                        } catch (Throwable $e) {
                        }
                    }
                });
            }
        } catch (Throwable $e) {
            // Ignore rollback errors
        }
        Schema::enableForeignKeyConstraints();
    }
};
