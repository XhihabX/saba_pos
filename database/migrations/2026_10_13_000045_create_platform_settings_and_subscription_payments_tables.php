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
        // 1. Platform Settings Table
        if (! Schema::hasTable('platform_settings')) {
            Schema::create('platform_settings', function (Blueprint $table) {
                $table->id();
                $table->string('key')->unique();
                $table->text('value')->nullable();
                $table->timestamps();
            });
        }

        // 2. Add sanitized bank and proof fields to Tenants table
        Schema::table('tenants', function (Blueprint $table) {
            if (! Schema::hasColumn('tenants', 'bank_name')) {
                $table->string('bank_name')->nullable()->after('transaction_id');
            }
            if (! Schema::hasColumn('tenants', 'account_holder')) {
                $table->string('account_holder')->nullable()->after('bank_name');
            }
            if (! Schema::hasColumn('tenants', 'account_last4')) {
                $table->string('account_last4', 4)->nullable()->after('account_holder');
            }
            if (! Schema::hasColumn('tenants', 'proof_path')) {
                $table->string('proof_path')->nullable()->after('account_last4');
            }
        });

        // 3. Subscription Payments Table
        if (! Schema::hasTable('subscription_payments')) {
            Schema::create('subscription_payments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
                $table->foreignId('plan_id')->constrained('saas_plans')->cascadeOnDelete();
                $table->decimal('amount', 12, 2);
                $table->string('method'); // bkash, nagad, rocket, bank
                $table->string('reference')->index();
                $table->string('bank_name')->nullable();
                $table->string('account_holder')->nullable();
                $table->string('account_last4', 4)->nullable();
                $table->string('proof_path')->nullable();
                $table->string('status')->default('pending')->index(); // pending, approved, rejected
                $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('reviewed_at')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscription_payments');

        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['bank_name', 'account_holder', 'account_last4', 'proof_path']);
        });

        Schema::dropIfExists('platform_settings');
    }
};
